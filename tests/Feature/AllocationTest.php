<?php

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\CommitteeSession;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\AllocationPublished;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => Notification::fake());

function verifiedDelegate(array $attributes = []): Delegate
{
    $registration = Registration::factory()->verified()->create();
    $user = User::factory()->create();

    return Delegate::factory()->for($registration)->create(['user_id' => $user->id, ...$attributes]);
}

test('only the registrations team and admins can open the conference pages', function (string $route, UserRole $role, int $status) {
    $this->actingAs(User::factory()->role($role)->create())
        ->get(route($route))
        ->assertStatus($status);
})->with(['staff.allocations', 'staff.committees', 'staff.guides', 'staff.announcements', 'staff.emails'])
    ->with([
        [UserRole::Registrations, 200],
        [UserRole::Admin, 200],
        [UserRole::Delegate, 403],
        [UserRole::Applicant, 403],
    ]);

test('the allocations page lists verified delegates only', function () {
    $verified = verifiedDelegate();
    $pending = Delegate::factory()->for(Registration::factory())->create();

    Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.allocations')
        ->assertSeeText($verified->name)
        ->assertDontSeeText($pending->name);
});

test('staff allocate a delegate using their preference, then send it', function () {
    $delegate = verifiedDelegate(['country_preference' => 'Pakistan']);
    $staff = User::factory()->role(UserRole::Registrations)->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.allocations')
        ->call('edit', $delegate->id)
        ->assertSet('committeeId', (string) $delegate->first_committee_id)
        ->assertSet('portfolio', 'Pakistan')
        ->call('save')
        ->assertHasNoErrors();

    $delegate->refresh();

    expect($delegate->allocated_committee_id)->toBe($delegate->first_committee_id)
        ->and($delegate->allocated_portfolio)->toBe('Pakistan')
        ->and($delegate->allocated_by)->toBe($staff->id)
        ->and($delegate->hasPublishedAllocation())->toBeFalse();

    Notification::assertNothingSent();

    Livewire::actingAs($staff)->test('pages::staff.allocations')->call('send', $delegate->id);

    expect($delegate->refresh()->hasPublishedAllocation())->toBeTrue();
    Notification::assertSentTo($delegate->user, AllocationPublished::class);
});

test('a portfolio cannot be given to two delegates in the same committee', function () {
    $committee = Committee::factory()->create();
    verifiedDelegate(['allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'France']);
    $delegate = verifiedDelegate();

    Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.allocations')
        ->call('edit', $delegate->id)
        ->set('committeeId', (string) $committee->id)
        ->set('portfolio', 'France')
        ->call('save')
        ->assertHasErrors('portfolio');

    expect($delegate->refresh()->allocated_committee_id)->toBeNull();
});

test('changing a sent allocation marks it unsent again', function () {
    $committee = Committee::factory()->create();
    $delegate = verifiedDelegate([
        'allocated_committee_id' => $committee->id,
        'allocated_portfolio' => 'Japan',
        'allocation_published_at' => now(),
    ]);

    Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.allocations')
        ->call('edit', $delegate->id)
        ->set('portfolio', 'Brazil')
        ->call('save');

    expect($delegate->refresh()->allocated_portfolio)->toBe('Brazil')
        ->and($delegate->allocation_published_at)->toBeNull();
});

test('sending all unsent allocations emails each allocated delegate once', function () {
    $committee = Committee::factory()->create();
    $unsent = verifiedDelegate(['allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'Chile']);
    $alreadySent = verifiedDelegate(['allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'Peru', 'allocation_published_at' => now()]);
    $unallocated = verifiedDelegate();

    Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.allocations')
        ->assertSet('show', 'unallocated')
        ->call('sendAll');

    Notification::assertSentTo($unsent->user, AllocationPublished::class);
    Notification::assertNotSentTo($alreadySent->user, AllocationPublished::class);
    Notification::assertNotSentTo($unallocated->user, AllocationPublished::class);
    expect($unsent->refresh()->hasPublishedAllocation())->toBeTrue();
});

test('delegates cannot call allocation actions', function () {
    $delegate = verifiedDelegate();

    Livewire::actingAs($delegate->user)
        ->test('pages::staff.allocations')
        ->call('edit', $delegate->id)
        ->assertForbidden();
});

test('a committee page lists its allocated delegates and sessions', function () {
    $committee = Committee::factory()->create(['code' => 'UNW']);
    $member = verifiedDelegate(['name' => 'Ayesha Khan', 'allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'Norway']);
    verifiedDelegate(['name' => 'Someone Else']);
    CommitteeSession::factory()->for($committee)->create(['title' => 'Opening session']);

    $this->actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->get(route('staff.committees.show', $committee))
        ->assertOk()
        ->assertSeeText('Ayesha Khan')
        ->assertSeeText('Norway')
        ->assertSeeText($member->registration->reference)
        ->assertDontSeeText('Someone Else')
        ->assertSeeText('Opening session');

    $this->actingAs(User::factory()->role(UserRole::Delegate)->create())
        ->get(route('staff.committees.show', $committee))
        ->assertForbidden();
});

test('a country committee only accepts a country from the list', function () {
    $committee = Committee::factory()->create();
    $delegate = verifiedDelegate();

    Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.allocations')
        ->call('edit', $delegate->id)
        ->set('committeeId', (string) $committee->id)
        ->set('portfolio', 'Frances')
        ->call('save')
        ->assertHasErrors(['portfolio' => 'in']);

    expect($delegate->refresh()->allocated_committee_id)->toBeNull();
});

test('a loosely typed country preference is matched to its listed name', function () {
    $delegate = verifiedDelegate(['country_preference' => 'usa']);

    Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.allocations')
        ->call('edit', $delegate->id)
        ->assertSet('portfolio', 'United States');
});

test('a character committee still takes any name', function () {
    $committee = Committee::factory()->characters()->create();
    $delegate = verifiedDelegate();

    Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.allocations')
        ->call('edit', $delegate->id)
        ->set('committeeId', (string) $committee->id)
        ->set('portfolio', 'Maryam Nawaz')
        ->call('save')
        ->assertHasNoErrors();

    expect($delegate->refresh()->allocated_portfolio)->toBe('Maryam Nawaz');
});

test('the allocations page picks up newly verified delegates without a reload', function () {
    $component = Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.allocations');

    $delegate = verifiedDelegate();

    $component->call('poll')->assertSeeText($delegate->name);
});

test('the allocations page does not refresh while a delegate is being allocated', function () {
    $editing = verifiedDelegate();

    $component = Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.allocations')
        ->call('edit', $editing->id);

    $stamp = $component->get('stamp');
    verifiedDelegate();

    $component->call('poll')->assertSet('stamp', $stamp);
});
