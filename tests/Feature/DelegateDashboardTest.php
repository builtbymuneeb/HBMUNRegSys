<?php

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\Committee;
use App\Models\CommitteeSession;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\StudyGuide;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function dashboardDelegate(array $attributes = [], bool $verified = true): Delegate
{
    $registration = $verified ? Registration::factory()->verified()->create() : Registration::factory()->create();

    return Delegate::factory()->for($registration)->create(['user_id' => User::factory()->create()->id, ...$attributes]);
}

function guideFor(?Committee $committee, string $title): StudyGuide
{
    return StudyGuide::create([
        'committee_id' => $committee?->id,
        'title' => $title,
        'file_path' => UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf')->store('study-guides', 'local'),
        'file_name' => 'guide.pdf',
        'file_size' => 10240,
    ]);
}

beforeEach(fn () => Storage::fake('local'));

test('an allocated delegate sees their allocation, session, card, guides and announcements', function () {
    $committee = Committee::factory()->create(['code' => 'UNSC', 'agenda' => 'Nuclear disarmament']);
    CommitteeSession::factory()->for($committee)->create(['title' => 'Opening session', 'meeting_url' => 'https://meet.google.com/abc-defg-hij']);
    $other = Committee::factory()->create();
    $delegate = dashboardDelegate(['allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'Pakistan', 'allocation_published_at' => now()]);

    guideFor(null, 'Rules of procedure');
    guideFor($committee, 'UNSC study guide');
    guideFor($other, 'Other committee guide');
    Announcement::create(['title' => 'Welcome everyone', 'body' => 'Hello!']);
    Announcement::create(['title' => 'UNSC update', 'body' => 'Roll call at 10.', 'committee_id' => $committee->id]);
    Announcement::create(['title' => 'Other update', 'body' => 'Hidden.', 'committee_id' => $other->id]);

    $this->actingAs($delegate->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Pakistan')
        ->assertSeeText('Nuclear disarmament')
        ->assertSeeText('Opening session')
        ->assertSee('https://meet.google.com/abc-defg-hij')
        ->assertSeeText('Download card')
        ->assertSeeText('Rules of procedure')
        ->assertSeeText('UNSC study guide')
        ->assertDontSeeText('Other committee guide')
        ->assertSeeText('Welcome everyone')
        ->assertSeeText('UNSC update')
        ->assertDontSeeText('Other update');
});

test('an unsent allocation stays hidden from the delegate', function () {
    $committee = Committee::factory()->create();
    CommitteeSession::factory()->for($committee)->create(['meeting_url' => 'https://meet.google.com/secret-link']);
    $delegate = dashboardDelegate(['allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'Kenya']);

    $this->actingAs($delegate->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Allocation pending')
        ->assertDontSeeText('Kenya')
        ->assertDontSee('secret-link');
});

test('a delegate whose payment is not verified sees nothing yet', function () {
    Announcement::create(['title' => 'Welcome everyone', 'body' => 'Hello!']);
    $delegate = dashboardDelegate(verified: false);

    $this->actingAs($delegate->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('on its way')
        ->assertDontSeeText('Welcome everyone');
});

test('delegates can download general guides and their own committee’s, but no others', function () {
    $committee = Committee::factory()->create();
    $other = Committee::factory()->create();
    $delegate = dashboardDelegate(['allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'Chad', 'allocation_published_at' => now()]);

    $this->actingAs($delegate->user);

    $this->get(route('study-guides.download', guideFor(null, 'General')))->assertOk();
    $this->get(route('study-guides.download', guideFor($committee, 'Mine')))->assertOk();
    $this->get(route('study-guides.download', guideFor($other, 'Other')))->assertForbidden();
});

test('guests cannot download study guides', function () {
    $this->get(route('study-guides.download', guideFor(null, 'General')))->assertRedirect(route('login'));
});

test('staff upload and delete study guides', function () {
    $staff = User::factory()->role(UserRole::Registrations)->create();
    $committee = Committee::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.guides')
        ->set('title', 'UNW Guide')
        ->set('committeeId', (string) $committee->id)
        ->set('file', UploadedFile::fake()->create('unw.pdf', 200, 'application/pdf'))
        ->call('saveGuide')
        ->assertHasNoErrors();

    $guide = StudyGuide::sole();
    expect($guide->committee_id)->toBe($committee->id)->and($guide->file_name)->toBe('unw.pdf');
    Storage::disk('local')->assertExists($guide->file_path);

    Livewire::actingAs($staff)->test('pages::staff.guides')->call('delete', $guide->id);

    expect(StudyGuide::count())->toBe(0);
    Storage::disk('local')->assertMissing($guide->file_path);
});

test('study guides must be documents', function () {
    Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.guides')
        ->set('title', 'Script')
        ->set('file', UploadedFile::fake()->create('evil.php', 1, 'text/x-php'))
        ->call('saveGuide')
        ->assertHasErrors('file');
});

test('staff post announcements and update committee details', function () {
    $staff = User::factory()->role(UserRole::Registrations)->create();
    $committee = Committee::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.announcements')
        ->set('title', 'Day 1 schedule')
        ->set('body', 'Sessions start at 10 am.')
        ->call('post')
        ->assertHasNoErrors();

    expect(Announcement::sole()->title)->toBe('Day 1 schedule');

    Livewire::actingAs($staff)
        ->test('pages::staff.committees.show', ['committee' => $committee])
        ->call('editAgenda')
        ->set('agenda', 'Women in peacebuilding')
        ->call('saveAgenda')
        ->assertHasNoErrors()
        ->call('addSession')
        ->set('sessionTitle', 'Session 1')
        ->set('sessionDate', '2026-11-21')
        ->set('startTime', '10:00')
        ->set('endTime', '09:00')
        ->set('meetingUrl', 'not a link')
        ->call('saveSession')
        ->assertHasErrors(['endTime', 'meetingUrl'])
        ->set('endTime', '12:30')
        ->set('meetingUrl', 'https://zoom.us/j/123')
        ->call('saveSession')
        ->assertHasNoErrors();

    $session = $committee->sessions()->sole();

    expect($committee->refresh()->agenda)->toBe('Women in peacebuilding')
        ->and($committee->name)->not->toBeEmpty()
        ->and($session->meeting_url)->toBe('https://zoom.us/j/123')
        ->and($session->localStart()->format('Y-m-d H:i'))->toBe('2026-11-21 10:00')
        ->and($session->starts_at->format('H:i'))->toBe('05:00')
        ->and($session->localEnd()->format('H:i'))->toBe('12:30');

    Livewire::actingAs($staff)
        ->test('pages::staff.committees.show', ['committee' => $committee])
        ->call('editSession', $session->id)
        ->assertSet('startTime', '10:00')
        ->set('meetingUrl', 'https://zoom.us/j/456')
        ->call('saveSession')
        ->call('addSession')
        ->assertSet('sessionTitle', 'Session 2')
        ->assertSet('startTime', '12:30')
        ->call('deleteSession', $session->id);

    expect($committee->sessions()->count())->toBe(0);
});

test('staff export delegates as CSV with formula-like values neutralised', function () {
    $delegate = dashboardDelegate(['name' => '=HYPERLINK("http://evil")']);

    $response = $this->actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->get(route('staff.delegates.export', ['status' => 'verified']));

    $response->assertOk()->assertDownload();
    $csv = $response->streamedContent();

    expect($csv)->toContain($delegate->registration->reference)
        ->toContain($delegate->email)
        ->toContain("'=HYPERLINK");
});

test('delegates cannot export', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('staff.delegates.export'))
        ->assertForbidden();
});

test('the delegate dashboard picks up new announcements and allocations without a reload', function () {
    $committee = Committee::factory()->create(['code' => 'UNSC']);
    $delegate = dashboardDelegate();

    $component = Livewire::actingAs($delegate->user)->test('delegate-dashboard')
        ->assertSeeText('Allocation pending')
        ->assertDontSeeText('Roll call moved');

    Announcement::create(['title' => 'Roll call moved', 'body' => 'Now at 11.']);
    $delegate->update(['allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'Pakistan', 'allocation_published_at' => now()]);
    cache()->forget('delegate-dashboard:stamp');

    $component->call('refresh')
        ->assertSeeText('Roll call moved')
        ->assertSeeText('Pakistan');
});
