<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffAccessApproved extends Notification implements ShouldQueue
{
    use Queueable;

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
        return (new MailMessage)
            ->subject('Your '.config('conference.name').' staff access is approved')
            ->greeting("Hello {$notifiable->name},")
            ->line("An admin approved your request. You’re now on the team as **{$notifiable->role->label()}**.")
            ->line($notifiable->role->description())
            ->action('Log in', route('login'));
    }
}
