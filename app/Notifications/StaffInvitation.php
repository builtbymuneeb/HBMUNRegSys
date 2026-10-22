<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitation extends Notification implements ShouldQueue
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
            ->subject('You’ve been added to the '.config('conference.name').' team')
            ->greeting("Hello {$notifiable->name},")
            ->line("You’ve been added to the organising team as **{$notifiable->role->label()}**.")
            ->line($notifiable->role->description())
            ->action('Set your password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire') / 60 .' hours. After that, use "Forgot password" on the login page.');
    }
}
