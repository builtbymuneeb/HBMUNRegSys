<?php

namespace App\Actions\Inbox;

use App\Models\CampusAmbassador;
use App\Models\InboxMessage;
use App\Models\Registration;
use DirectoryTree\ImapEngine\Mailbox;
use DirectoryTree\ImapEngine\MailboxInterface;
use DirectoryTree\ImapEngine\MessageInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Copies new mail from the contact@ mailbox into the inbox_messages table, linking each one to the
 * ambassador or registration it came from. Messages are only read: they stay unread on the mail server.
 */
class FetchInbox
{
    /**
     * How far back the first fetch looks.
     */
    public const FIRST_FETCH_DAYS = 60;

    /**
     * Cache key holding when the mailbox was last checked.
     */
    public const LAST_FETCHED_KEY = 'inbox.last_fetched_at';

    /**
     * The contact@ mailbox, from config/mail.php's "inbox" settings.
     */
    public static function mailbox(): MailboxInterface
    {
        return new Mailbox([
            'host' => config('mail.inbox.host'),
            'port' => (int) config('mail.inbox.port'),
            'encryption' => config('mail.inbox.encryption'),
            'username' => config('mail.inbox.username'),
            'password' => config('mail.inbox.password'),
        ]);
    }

    /**
     * Store every message received since the last fetch that isn't stored yet, and return how many were new.
     */
    public function fetch(MailboxInterface $mailbox): int
    {
        $latest = InboxMessage::incoming()->max('received_at');
        $since = $latest ? now()->parse($latest)->subDay() : now()->subDays(self::FIRST_FETCH_DAYS);
        $ownAddress = Str::lower((string) config('mail.from.address'));
        $new = 0;

        $mailbox->inbox()->messages()
            ->since($since)
            ->withHeaders()
            ->withBody()
            ->leaveUnread()
            ->each(function (MessageInterface $message) use ($ownAddress, &$new) {
                $from = $message->from();
                $email = Str::lower((string) $from?->email());

                if ($email === '' || $email === $ownAddress) {
                    return;
                }

                $messageId = $message->messageId() ?: sha1($email.'|'.$message->date()?->toIso8601String().'|'.$message->subject());

                if (InboxMessage::where('message_id', $messageId)->exists()) {
                    return;
                }

                InboxMessage::create([
                    'direction' => InboxMessage::IN,
                    'message_id' => Str::limit($messageId, 255, ''),
                    'in_reply_to' => Str::limit((string) ($message->inReplyTo()[0] ?? ''), 255, '') ?: null,
                    'email' => $email,
                    'name' => $from->name() ?: null,
                    'subject' => Str::limit((string) $message->subject(), 255, '') ?: null,
                    'body' => $this->body($message),
                    'received_at' => $message->date() ?? now(),
                    'campus_ambassador_id' => CampusAmbassador::whereRaw('lower(email) = ?', [$email])->value('id'),
                    'registration_id' => Registration::whereRaw('lower(contact_email) = ?', [$email])->latest('id')->value('id'),
                ]);

                $new++;
            });

        Cache::forever(self::LAST_FETCHED_KEY, now()->getTimestamp());

        return $new;
    }

    /**
     * The message as plain text, falling back to the HTML part with its tags removed.
     */
    protected function body(MessageInterface $message): string
    {
        $text = $message->text();

        if (blank($text) && filled($html = $message->html())) {
            $html = preg_replace(['/<(style|script|head)\b.*?<\/\1>/is', '/<br\s*\/?>/i', '/<\/(p|div|tr|li|h\d)>/i'], ['', "\n", "\n"], $html);
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", (string) $text)));
    }
}
