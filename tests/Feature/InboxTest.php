<?php

use App\Actions\Inbox\FetchInbox;
use App\Enums\UserRole;
use App\Models\CampusAmbassador;
use App\Models\InboxMessage;
use App\Models\User;
use App\Notifications\InboxReply;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * A raw email as it would sit in the contact@ mailbox.
 */
function rawEmail(string $from, string $subject, string $body, string $messageId, string $date = 'now'): string
{
    return implode("\r\n", [
        "From: {$from}",
        'To: contact@hbmun.info',
        "Subject: {$subject}",
        'Date: '.now()->parse($date)->toRfc2822String(),
        "Message-ID: {$messageId}",
        'Content-Type: text/plain; charset=UTF-8',
        '',
        $body,
    ]);
}

function fakeInbox(string ...$emails): FakeMailbox
{
    $messages = array_map(fn (string $raw, int $index) => new FakeMessage($index + 1, [], $raw), $emails, array_keys($emails));

    return new FakeMailbox(folders: [new FakeFolder('inbox', messages: $messages)]);
}

test('new mail is copied into the inbox once, linked to the ambassador who sent it', function () {
    config(['mail.from.address' => 'contact@hbmun.info']);
    $ambassador = CampusAmbassador::factory()->create(['code' => 'HB07', 'name' => 'Ayesha Khan', 'email' => 'ayesha@example.com']);

    $mailbox = fakeInbox(
        rawEmail('Ayesha Khan <Ayesha@Example.com>', 'Re: Welcome to the team', "Thanks! Count me in.\n\nOn Fri, 30 Oct 2026 Homebridge MUN wrote:\n> Welcome aboard!", '<reply-1@mail.example.com>'),
        rawEmail('Someone Else <someone@example.org>', 'Question about fees', 'How much is the fee?', '<q-1@mail.example.org>'),
        rawEmail('Homebridge MUN <contact@hbmun.info>', 'Our own copy', 'Ignore me', '<own@hbmun.info>'),
    );

    expect(app(FetchInbox::class)->fetch($mailbox))->toBe(2)
        ->and(app(FetchInbox::class)->fetch($mailbox))->toBe(0);

    $reply = InboxMessage::where('email', 'ayesha@example.com')->sole();
    expect($reply->campus_ambassador_id)->toBe($ambassador->id)
        ->and($reply->subject)->toBe('Re: Welcome to the team')
        ->and($reply->bodyWithoutQuote())->toBe('Thanks! Count me in.')
        ->and($reply->read_at)->toBeNull()
        ->and(InboxMessage::where('email', 'someone@example.org')->sole()->campus_ambassador_id)->toBeNull();
});

test('staff see conversations with unread counts, and opening one marks it read', function () {
    $ambassador = CampusAmbassador::factory()->create(['code' => 'HB07', 'name' => 'Ayesha Khan', 'email' => 'ayesha@example.com']);
    InboxMessage::factory()->create(['email' => 'ayesha@example.com', 'campus_ambassador_id' => $ambassador->id, 'subject' => 'First question', 'received_at' => now()->subHour()]);
    $latest = InboxMessage::factory()->create(['email' => 'ayesha@example.com', 'campus_ambassador_id' => $ambassador->id, 'subject' => 'Second question']);
    InboxMessage::factory()->read()->create(['email' => 'other@example.org', 'name' => 'Other Person', 'subject' => 'Already read']);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.inbox.index')
        ->assertSeeText(['Ayesha Khan', 'HB07', 'Second question', 'Other Person'])
        ->assertDontSeeText('First question')
        ->set('show', 'unread')
        ->assertSeeText('Ayesha Khan')
        ->assertDontSeeText('Other Person');

    Livewire::actingAs(staffMember())
        ->test('pages::staff.inbox.show', ['message' => $latest])
        ->assertSeeText(['First question', 'Second question', 'Campus ambassador HB07']);

    expect(InboxMessage::unread()->count())->toBe(0);
});

test('a reply is emailed in the same thread and shown in the conversation', function () {
    Notification::fake();
    $message = InboxMessage::factory()->create(['email' => 'ayesha@example.com', 'name' => 'Ayesha Khan', 'subject' => 'Question about fees', 'message_id' => '<q-1@mail.example.com>']);

    Livewire::actingAs($staff = staffMember())
        ->test('pages::staff.inbox.show', ['message' => $message])
        ->call('sendReply')
        ->assertHasErrors('reply')
        ->set('reply', "The fee is PKR 1,000 per delegate.\n\nSee you there!")
        ->call('sendReply')
        ->assertHasNoErrors()
        ->assertSeeText('The fee is PKR 1,000 per delegate.');

    Notification::assertSentOnDemand(InboxReply::class, function (InboxReply $notification, array $channels, AnonymousNotifiable $notifiable) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'ayesha@example.com'
            && $mail->subject === 'Re: Question about fees'
            && $mail->greeting === 'Hello Ayesha,'
            && $mail->introLines === ['The fee is PKR 1,000 per delegate.', 'See you there!'];
    });

    $sent = InboxMessage::where('direction', InboxMessage::OUT)->sole();
    expect($sent->email)->toBe('ayesha@example.com')
        ->and($sent->sent_by)->toBe($staff->id);
});

test('ambassador managers only see mail from ambassadors; other users cannot open the inbox', function () {
    $ambassador = CampusAmbassador::factory()->create(['name' => 'Ayesha Khan', 'email' => 'ayesha@example.com']);
    InboxMessage::factory()->create(['email' => 'ayesha@example.com', 'campus_ambassador_id' => $ambassador->id]);
    $private = InboxMessage::factory()->create(['email' => 'parent@example.org', 'name' => 'A Parent']);

    $ambassadorManager = User::factory()->role(UserRole::Ambassadors)->create();

    Livewire::actingAs($ambassadorManager)
        ->test('pages::staff.inbox.index')
        ->assertSeeText('Ayesha Khan')
        ->assertDontSeeText('A Parent');

    $this->actingAs($ambassadorManager)->get(route('staff.inbox.show', $private))->assertNotFound();

    $this->actingAs(User::factory()->create())->get(route('staff.inbox'))->assertForbidden();
});
