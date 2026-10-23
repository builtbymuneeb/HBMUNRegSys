<?php

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\CommitteeSession;
use App\Models\Delegate;
use App\Models\PushSubscription;
use App\Models\Registration;
use App\Models\StudyGuide;
use App\Models\User;
use App\Notifications\DashboardUpdate;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

function subscribedDelegate(array $attributes = [], bool $verified = true): Delegate
{
    $registration = $verified ? Registration::factory()->verified()->create() : Registration::factory()->create();
    $delegate = Delegate::factory()->for($registration)->create(['user_id' => User::factory()->create()->id, ...$attributes]);

    PushSubscription::create([
        'user_id' => $delegate->user_id,
        'endpoint' => 'https://push.example.com/'.$delegate->id,
        'public_key' => 'p256dh-key',
        'auth_token' => 'auth-secret',
    ]);

    return $delegate;
}

function pushedTag(User $user): ?string
{
    $tag = null;
    Notification::assertSentTo($user, DashboardUpdate::class, function (DashboardUpdate $notification) use (&$tag) {
        $tag = $notification->tag;

        return true;
    });

    return $tag;
}

test('a delegate saves their browser’s push subscription', function () {
    $user = User::factory()->create();
    $subscription = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'secret'], 'contentEncoding' => 'aes128gcm'];

    $this->actingAs($user)->postJson(route('push-subscriptions.store'), $subscription)->assertNoContent();
    $this->actingAs($user)->postJson(route('push-subscriptions.store'), $subscription)->assertNoContent();

    expect(PushSubscription::sole())
        ->user_id->toBe($user->id)
        ->public_key->toBe('key')
        ->auth_token->toBe('secret');

    // The same browser signed in as someone else now belongs to them.
    $other = User::factory()->create();
    $this->actingAs($other)->postJson(route('push-subscriptions.store'), $subscription)->assertNoContent();
    expect(PushSubscription::sole()->user_id)->toBe($other->id);

    $this->actingAs($other)->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => $subscription['endpoint']])->assertNoContent();
    expect(PushSubscription::count())->toBe(0);
});

test('push subscriptions need a signed-in user and a valid https endpoint', function () {
    $this->postJson(route('push-subscriptions.store'), ['endpoint' => 'https://push.example.com/x', 'keys' => ['p256dh' => 'k', 'auth' => 'a']])
        ->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson(route('push-subscriptions.store'), ['endpoint' => 'http://push.example.com/x'])
        ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
});

test('announcements and study guides are pushed only to delegates who can see them', function () {
    Notification::fake();
    Storage::fake('local');
    $committee = Committee::factory()->create();
    $staff = User::factory()->role(UserRole::Registrations)->create();

    $inCommittee = subscribedDelegate(['allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'Kenya', 'allocation_published_at' => now()]);
    $unpublished = subscribedDelegate(['allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'Peru']);
    $unverified = subscribedDelegate(verified: false);
    $notSubscribed = Delegate::factory()->for(Registration::factory()->verified())->create(['user_id' => User::factory()->create()->id]);

    Livewire::actingAs($staff)->test('pages::staff.announcements')
        ->set('title', 'Welcome everyone')->set('body', 'Hello!')
        ->call('post');

    expect(pushedTag($inCommittee->user))->toStartWith('announcement-')
        ->and(pushedTag($unpublished->user))->toStartWith('announcement-');
    Notification::assertNotSentTo([$unverified->user, $notSubscribed->user], DashboardUpdate::class);

    Notification::fake();

    Livewire::actingAs($staff)->test('pages::staff.guides')
        ->set('title', 'Committee guide')
        ->set('committeeId', (string) $committee->id)
        ->set('file', UploadedFile::fake()->create('guide.pdf', 20, 'application/pdf'))
        ->call('saveGuide')
        ->assertHasNoErrors();

    Notification::assertSentTo($inCommittee->user, DashboardUpdate::class, fn (DashboardUpdate $notification) => $notification->title === 'New study guide'
        && $notification->body === 'Committee guide'
        && $notification->tag === 'guide-'.StudyGuide::sole()->id);
    Notification::assertNotSentTo([$unpublished->user, $unverified->user], DashboardUpdate::class);
});

test('sending an allocation pushes it to the delegate', function () {
    Notification::fake();
    $committee = Committee::factory()->create(['code' => 'UNSC']);
    $delegate = subscribedDelegate(['allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'Pakistan']);

    Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.allocations')
        ->call('send', $delegate->id);

    Notification::assertSentTo($delegate->user, DashboardUpdate::class, fn (DashboardUpdate $notification) => $notification->tag === 'allocation'
        && $notification->body === 'Pakistan · '.$committee->label());
});

test('new and rescheduled sessions are pushed to the committee, but renames are not', function () {
    Notification::fake();
    $committee = Committee::factory()->create();
    $delegate = subscribedDelegate(['allocated_committee_id' => $committee->id, 'allocated_portfolio' => 'Kenya', 'allocation_published_at' => now()]);
    $elsewhere = subscribedDelegate(['allocated_committee_id' => Committee::factory()->create()->id, 'allocated_portfolio' => 'Peru', 'allocation_published_at' => now()]);
    $staff = User::factory()->role(UserRole::Registrations)->create();

    Livewire::actingAs($staff)->test('pages::staff.committees.show', ['committee' => $committee])
        ->call('addSession')
        ->set('sessionTitle', 'Session 1')
        ->set('sessionDate', '2026-11-21')
        ->set('startTime', '10:00')
        ->set('endTime', '12:00')
        ->call('saveSession')
        ->assertHasNoErrors();

    $session = CommitteeSession::sole();
    Notification::assertSentTo($delegate->user, DashboardUpdate::class, fn (DashboardUpdate $notification) => $notification->title === 'New session scheduled'
        && $notification->tag === 'session-'.$session->id);
    Notification::assertNotSentTo($elsewhere->user, DashboardUpdate::class);

    Notification::fake();

    Livewire::actingAs($staff)->test('pages::staff.committees.show', ['committee' => $committee])
        ->call('editSession', $session->id)
        ->set('sessionTitle', 'Opening session')
        ->call('saveSession');

    Notification::assertNothingSent();

    Livewire::actingAs($staff)->test('pages::staff.committees.show', ['committee' => $committee])
        ->call('editSession', $session->id)
        ->set('startTime', '11:00')
        ->call('saveSession');

    Notification::assertSentTo($delegate->user, DashboardUpdate::class, fn (DashboardUpdate $notification) => $notification->title === 'Session updated'
        && $notification->tag === 'session-'.$session->id);
});

test('pushes go to each of the user’s browsers and expired ones are forgotten', function () {
    config(['services.webpush.private_key' => 'configured']);
    $delegate = subscribedDelegate();
    PushSubscription::create(['user_id' => $delegate->user_id, 'endpoint' => 'https://push.example.com/gone', 'public_key' => 'k', 'auth_token' => 'a']);

    $sent = [];
    $this->mock(WebPush::class, function ($mock) use (&$sent) {
        $mock->shouldReceive('queueNotification')->twice()->andReturnUsing(function (Subscription $subscription, string $payload, array $options) use (&$sent) {
            $sent[] = [$subscription->getEndpoint(), json_decode($payload, true), $options['topic']];
        });
        $mock->shouldReceive('flush')->once()->andReturnUsing(fn () => (function () {
            yield new MessageSentReport(new Request('POST', 'https://push.example.com/'.Delegate::first()->id), new Response(201));
            yield new MessageSentReport(new Request('POST', 'https://push.example.com/gone'), new Response(410), false, 'Gone');
        })());
    });

    $delegate->user->notify(new DashboardUpdate('New announcement', 'Day 1 schedule', 'announcement-7'));

    expect($sent)->toHaveCount(2)
        ->and($sent[0][1])->toBe(['title' => 'New announcement', 'body' => 'Day 1 schedule', 'tag' => 'announcement-7', 'url' => '/dashboard'])
        ->and($sent[0][2])->toBe('announcement-7')
        ->and(PushSubscription::pluck('endpoint')->all())->toBe(['https://push.example.com/'.$delegate->id]);
});

test('nothing is pushed when VAPID keys are not configured', function () {
    config(['services.webpush.private_key' => null]);
    $delegate = subscribedDelegate();

    $this->mock(WebPush::class)->shouldNotReceive('queueNotification');

    $delegate->user->notify(new DashboardUpdate('New announcement', 'Hi', 'announcement-1'));
});
