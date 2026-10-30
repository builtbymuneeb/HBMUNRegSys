<?php

namespace App\Notifications;

use App\Models\InboxMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

/**
 * A reply staff write on the inbox page to an email someone sent the contact@ mailbox.
 * It threads under their message in their mail app.
 */
class InboxReply extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public InboxMessage $original, public string $body) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(self::subjectFor($this->original))
            ->greeting($this->original->name || $this->original->ambassador
                ? 'Hello '.Str::before(trim($this->original->displayName()), ' ').','
                : 'Hello,');

        foreach (preg_split('/\R{2,}/', trim($this->body)) as $paragraph) {
            $mail->line($paragraph);
        }

        $messageId = $this->original->message_id;

        return $mail->salutation('The '.config('conference.name').' team')
            ->withSymfonyMessage(function (Email $email) use ($messageId) {
                if ($messageId && str_starts_with($messageId, '<')) {
                    $email->getHeaders()->addIdHeader('In-Reply-To', trim($messageId, '<>'));
                    $email->getHeaders()->addIdHeader('References', trim($messageId, '<>'));
                }
            });
    }

    /**
     * "Re: their subject", without stacking "Re: Re:".
     */
    public static function subjectFor(InboxMessage $original): string
    {
        $subject = trim((string) $original->subject) ?: 'Your message to '.config('conference.name');

        return Str::startsWith(Str::lower($subject), 're:') ? $subject : 'Re: '.$subject;
    }
}
