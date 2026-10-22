<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationRejected extends Notification implements ShouldQueue
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
            ->subject("Action needed on your registration ({$this->registration->reference})")
            ->greeting("Hello {$this->registration->contact_name},")
            ->line('We could not verify the payment for your registration.')
            ->line("**Reason:** {$this->registration->rejection_reason}")
            ->line('You can upload a new payment screenshot from the status page using your reference number and email.')
            ->action('Upload a new screenshot', route('registration.status'));
    }
}
