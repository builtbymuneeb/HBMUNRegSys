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

    /**
     * The same message as plain text, for staff to send by WhatsApp.
     */
    public function toWhatsApp(): string
    {
        return implode("\n\n", [
            "Hello {$this->registration->contact_name},",
            "The payment for {$this->registration->displayName()} ({$this->registration->reference}) has been verified.",
            'Each delegate has been sent an email to set their password and access their delegate dashboard. Please ask them to check their inbox and spam folder.',
            'We look forward to welcoming your delegation to '.config('conference.name').'!',
        ]);
    }
}
