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
     * Create a new notification instance.
     */
    public function __construct(public string $token) {}

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

        return (new MailMessage)
            ->subject('Your '.config('conference.name').' registration is verified')
            ->greeting("Welcome, {$notifiable->name}!")
            ->line('Your payment has been verified and your delegate account is ready.')
            ->line('Set a password to log in to your delegate dashboard, where your committee, portfolio, study guides and session links will appear.')
            ->action('Set your password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire') / 60 .' hours. After that, use "Forgot password" on the login page to get a new one.');
    }
}
