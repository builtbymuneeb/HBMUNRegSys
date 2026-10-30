<?php

namespace App\Notifications;

use App\Models\CampusAmbassador;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcomes a campus ambassador and gives them their referral code and personal registration link.
 */
class AmbassadorWelcome extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CampusAmbassador $ambassador) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $code = $this->ambassador->code;

        return (new MailMessage)
            ->subject('Welcome to the '.config('conference.name').' Campus Ambassador team')
            ->greeting("Hello {$this->ambassador->firstName()},")
            ->line('Welcome aboard! You are now a Campus Ambassador for '.config('conference.name').', taking place on '.config('conference.dates_label').'.')
            ->line("Your referral code is **{$code}**.")
            ->line("Share it with students at your campus. When they register and enter {$code} as the referral code, their delegation counts for you. The link below fills your code in for them:")
            ->action('Your registration link', $this->ambassador->registrationLink())
            ->line('If you have any questions, just reply to this email.')
            ->salutation('Thank you for representing '.config('conference.name').'!');
    }

    /**
     * The same message as plain text, for staff to send by WhatsApp.
     */
    public function toWhatsApp(): string
    {
        $code = $this->ambassador->code;

        return implode("\n\n", [
            "Hello {$this->ambassador->firstName()},",
            'Welcome aboard! You are now a Campus Ambassador for '.config('conference.name').' ('.config('conference.dates_label').').',
            "Your referral code is *{$code}*.",
            "Share it with students at your campus. When they register and enter {$code} as the referral code, their delegation counts for you. This link fills your code in for them:\n".$this->ambassador->registrationLink(),
            'Thank you for representing '.config('conference.name').'!',
        ]);
    }
}
