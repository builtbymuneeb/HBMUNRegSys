<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a campus ambassador their portal is ready. Sends a set-password link when
 * they have no password yet, or just a login link when they already have an account.
 */
class AmbassadorPortalInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $token) {}

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
            ->subject('Your '.config('conference.name').' Campus Ambassador portal is ready')
            ->greeting('Hello '.str($notifiable->name)->before(' ').',')
            ->line('You now have your own Campus Ambassador portal. Staff will post tasks there: mark each one as done, upload a screenshot or other proof, and earn points once it is approved.')
            ->line('You also earn points for every verified delegate who registers with your referral code. See where you stand on the leaderboard!');

        if ($this->token !== null) {
            return $mail
                ->action('Set your password', route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]))
                ->line('This link expires in '.config('auth.passwords.users.expire') / 60 .' hours. After that, use "Forgot password" on the login page with this email address.');
        }

        return $mail
            ->line("Log in with your existing account ({$notifiable->email}).")
            ->action('Open the portal', route('ambassador.tasks'));
    }

    /**
     * A WhatsApp nudge to go with the email. The password link stays in the email, since it is a login secret.
     */
    public static function whatsAppMessage(string $firstName, string $email): string
    {
        return implode("\n\n", [
            "Hello {$firstName},",
            'Your '.config('conference.name').' Campus Ambassador portal is ready! Staff will post tasks there: mark them done, upload proof and earn points on the leaderboard.',
            "We have emailed a link to set your password to *{$email}*. Please check your inbox and spam folder.",
            'If the link has expired, use "Forgot password" on the login page: '.route('password.request'),
        ]);
    }
}
