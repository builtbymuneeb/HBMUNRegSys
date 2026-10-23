<?php

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\Delegate;
use App\Models\MailLog;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\AllocationPublished;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function sentAllocationDelegate(): Delegate
{
    $committee = Committee::factory()->create(['code' => 'UNW']);

    return Delegate::factory()->for(Registration::factory()->verified())->create([
        'user_id' => User::factory()->create()->id,
        'allocated_committee_id' => $committee->id,
        'allocated_portfolio' => 'Canada',
    ]);
}

test('every email that goes out is logged with its recipient and type', function () {
    config(['mail.default' => 'array']);
    $delegate = sentAllocationDelegate();

    Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.allocations')
        ->set('show', 'all')
        ->call('send', $delegate->id);

    $log = MailLog::sole();

    expect($log->status)->toBe(MailLog::SENT)
        ->and($log->to)->toBe($delegate->user->email)
        ->and($log->notification)->toBe(AllocationPublished::class)
        ->and($log->typeLabel())->toBe('Allocation Published');
});

test('an email that fails for good is logged with the error', function () {
    $delegate = sentAllocationDelegate();
    $command = new SendQueuedNotifications(collect([$delegate->user]), new AllocationPublished($delegate), ['mail']);
    $job = new SyncJob(app(), json_encode(['data' => ['command' => serialize($command)]]), 'sync', 'sync');

    event(new JobFailed('database', $job, new RuntimeException('Expected response code 250 but got 550')));

    $log = MailLog::sole();

    expect($log->status)->toBe(MailLog::FAILED)
        ->and($log->to)->toBe($delegate->user->email)
        ->and($log->error)->toContain('550');
});

test('staff see sent and failed emails in the email log', function () {
    MailLog::create(['status' => MailLog::SENT, 'to' => 'ok@example.com', 'subject' => 'Your allocation', 'notification' => AllocationPublished::class]);
    MailLog::create(['status' => MailLog::FAILED, 'to' => 'bad@example.com', 'notification' => AllocationPublished::class, 'error' => 'Mailbox unavailable']);

    $this->actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->get(route('staff.emails'))
        ->assertOk()
        ->assertSeeText('ok@example.com')
        ->assertSeeText('bad@example.com')
        ->assertSeeText('Mailbox unavailable');
});

test('the allocations page shows whether each sent allocation was emailed', function () {
    Notification::fake();
    $delegate = sentAllocationDelegate();
    $delegate->forceFill(['allocation_published_at' => now()])->save();

    $page = Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.allocations')
        ->set('show', 'sent');

    $page->assertSeeText('Email waiting to send');

    MailLog::create(['status' => MailLog::FAILED, 'to' => $delegate->user->email, 'notification' => AllocationPublished::class, 'error' => 'boom']);

    $page->call('$refresh')->assertSeeText('Email failed');
});
