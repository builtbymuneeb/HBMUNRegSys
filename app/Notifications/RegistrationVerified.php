<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationVerified extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Registration $registration) {}

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
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Registration verified ({$this->registration->reference})")
            ->greeting("Hello {$this->registration->contact_name},")
            ->line("The payment for {$this->registration->displayName()} has been verified.")
            ->line('Each delegate has been sent an email to set their password and access their delegate dashboard.')
            ->line('We look forward to welcoming your delegation to '.config('conference.name').'!');
    }
}
