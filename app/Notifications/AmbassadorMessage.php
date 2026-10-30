<?php

namespace App\Notifications;

use App\Models\CampusAmbassador;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A message staff write to one or more campus ambassadors. {name}, {code} and {link}
 * in the subject or text become each ambassador's first name, referral code and registration link.
 */
class AmbassadorMessage extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The placeholders staff can use, with what each becomes.
     */
    public const PLACEHOLDERS = [
        '{name}' => 'first name',
        '{code}' => 'referral code',
        '{link}' => 'registration link with their code',
    ];

    public function __construct(public CampusAmbassador $ambassador, public string $subject, public string $body) {}

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
            ->subject($this->personalise($this->subject))
            ->greeting("Hello {$this->ambassador->firstName()},");

        foreach (preg_split('/\R{2,}/', trim($this->personalise($this->body))) as $paragraph) {
            $mail->line($paragraph);
        }

        return $mail->salutation('The '.config('conference.name').' team');
    }

    /**
     * The same message as plain text, for staff to send by WhatsApp.
     */
    public function toWhatsApp(): string
    {
        return "Hello {$this->ambassador->firstName()},\n\n".trim($this->personalise($this->body));
    }

    private function personalise(string $text): string
    {
        return strtr($text, [
            '{name}' => $this->ambassador->firstName(),
            '{code}' => $this->ambassador->code,
            '{link}' => $this->ambassador->registrationLink(),
        ]);
    }
}
