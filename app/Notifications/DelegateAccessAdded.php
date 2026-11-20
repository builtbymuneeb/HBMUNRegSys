<?php

namespace App\Notifications;

use App\Models\Registration;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a verified delegate already had an account (for example a campus
 * ambassador). They keep their password, so there is no set-password link.
 */
class DelegateAccessAdded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Registration $registration) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Registration verified ({$this->registration->reference})")
            ->greeting('Hello '.str($notifiable->name)->before(' ').',')
            ->line('Your delegate registration has been verified. Welcome to '.config('conference.name').'!')
            ->line('Your delegate dashboard is now part of the account you already have. Your committee allocation, sessions, study guides and announcements will appear there.')
            ->line("Log in as usual with {$notifiable->email} and your current password.");

        if ($notifiable->ambassador()->exists()) {
            $mail->line('Your campus ambassador portal stays on the same account, so you can keep doing tasks and earning points.');
        }

        return $mail
            ->action('Open your dashboard', route('dashboard'))
            ->line('Forgot your password? Use "Forgot password" on the login page with this email address.');
    }
}
