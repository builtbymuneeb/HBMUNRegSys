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
     * With $refresh, look back as far as the first fetch did and re-read the text of messages already stored.
     */
    public function fetch(MailboxInterface $mailbox, bool $refresh = false): int
    {
        $latest = $refresh ? null : InboxMessage::incoming()->max('received_at');
        $since = $latest ? now()->parse($latest)->subDay() : now()->subDays(self::FIRST_FETCH_DAYS);
        $ownAddress = Str::lower((string) config('mail.from.address'));
        $new = 0;

        $mailbox->inbox()->messages()
            ->since($since)
            ->withHeaders()
            ->withBody()
            ->leaveUnread()
            ->each(function (MessageInterface $message) use ($ownAddress, $refresh, &$new) {
                $from = $message->from();
                $email = Str::lower((string) $from?->email());

                if ($email === '' || $email === $ownAddress) {
                    return;
                }

                $messageId = $message->messageId() ?: sha1($email.'|'.$message->date()?->toIso8601String().'|'.$message->subject());

                if ($stored = InboxMessage::firstWhere('message_id', $messageId)) {
                    if ($refresh) {
                        $stored->update(['body' => $this->body($message), 'html' => $this->html($message)]);
                    }

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
                    'html' => $this->html($message),
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
     * The message as plain text. Many senders put HTML in the text part too, or send no text part at all,
     * so HTML is turned into text whenever that is what we were given.
     */
    protected function body(MessageInterface $message): string
    {
        $text = (string) $message->text();

        if (blank($text) || self::looksLikeHtml($text)) {
            $text = self::htmlToText(filled($html = $message->html()) ? $html : $text);
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", $text)));
    }

    /**
     * The message's HTML part, or the text part when that is really HTML.
     */
    protected function html(MessageInterface $message): ?string
    {
        $html = $message->html();

        if (blank($html) && self::looksLikeHtml((string) $message->text())) {
            $html = $message->text();
        }

        return filled($html) ? $html : null;
    }

    public static function looksLikeHtml(string $text): bool
    {
        return (bool) preg_match('/<(!doctype|html|body|table|div|p|br)\b/i', $text);
    }

    /**
     * Readable text from an HTML email: hidden preview text, styles and scripts dropped, block ends turned into new lines.
     */
    public static function htmlToText(string $html): string
    {
        $html = preg_replace([
            '/<(style|script|head|title)\b.*?<\/\1>/is',
            '/<(div|span)\b[^>]*display\s*:\s*none[^>]*>.*?<\/\1>/is',
            '/<br\s*\/?>/i',
            '/<\/(div|tr|li|table)>/i',
            '/<\/(p|h\d)>/i',
        ], ['', '', "\n", "\n", "\n\n"], $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace(['/[\x{00AD}\x{034F}\x{200B}-\x{200D}\x{2007}\x{FEFF}]/u', '/[ \t\x{00A0}]+/u', '/ *\n */'], ['', ' ', "\n"], $text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
