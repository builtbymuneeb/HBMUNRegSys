<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * When this is a resend, the Pakistan time it was sent (e.g. "17 Nov, 5:37 pm"). A resend gets
     * its own subject with that time, so Gmail shows it as a new email instead of tucking it into
     * the old thread, where people kept tapping the old (cancelled) link.
     */
    public ?string $resentAt = null;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $token, bool $resend = false)
    {
        if ($resend) {
            $this->resentAt = now('Asia/Karachi')->format('j M, g:i a');
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        if ($this->resentAt !== null) {
            return (new MailMessage)
                ->subject('Your new password link for '.config('conference.name')." (sent {$this->resentAt})")
                ->greeting("Hello {$notifiable->name},")
                ->line('Here is a fresh link to set your password for your delegate dashboard.')
                ->line('**Use this email, not any older one.** Earlier links stopped working when this one was sent.')
                ->action('Set your password', $url)
                ->line('This link expires in '.intdiv(config('auth.passwords.users.expire'), 1440).' days. After that, use "Forgot password" on the login page to get a new one.');
        }

        return (new MailMessage)
            ->subject('Your '.config('conference.name').' registration is verified')
            ->greeting("Welcome, {$notifiable->name}!")
            ->line('Your payment has been verified and your delegate account is ready.')
            ->line('Set a password to log in to your delegate dashboard, where your committee, portfolio, study guides and session links will appear.')
            ->action('Set your password', $url)
            ->line('This link expires in '.intdiv(config('auth.passwords.users.expire'), 1440).' days, and only the newest link we send works. After that, use "Forgot password" on the login page to get a new one.');
    }

    /**
     * A WhatsApp nudge to go with this email. The password link itself stays in the email,
     * since it is a login secret; the message points them to it or to "Forgot password".
     */
    public static function whatsAppMessage(string $name, string $email): string
    {
        return implode("\n\n", [
            "Hello {$name},",
            'Your payment for '.config('conference.name').' has been verified and your delegate account is ready.',
            "We have emailed a link to set your password to *{$email}*. Please check your inbox and spam folder.",
            'If the link has expired, use "Forgot password" on the login page: '.route('password.request'),
        ]);
    }
}
