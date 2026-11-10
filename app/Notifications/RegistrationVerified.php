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
        if ($this->registration->type->isIndividual()) {
            return (new MailMessage)
                ->subject("Registration verified ({$this->registration->reference})")
                ->greeting("Hello {$this->registration->contact_name},")
                ->line('Your payment has been verified.')
                ->line('You have been sent a separate email to set your password and access your delegate dashboard.')
                ->line('We look forward to welcoming you to '.config('conference.name').'!');
        }

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
        if ($this->registration->type->isIndividual()) {
            return implode("\n\n", [
                "Hello {$this->registration->contact_name},",
                "Your payment for {$this->registration->reference} has been verified.",
                'You have been sent an email to set your password and access your delegate dashboard. Please check your inbox and spam folder.',
                'We look forward to welcoming you to '.config('conference.name').'!',
            ]);
        }

        return implode("\n\n", [
            "Hello {$this->registration->contact_name},",
            "The payment for {$this->registration->displayName()} ({$this->registration->reference}) has been verified.",
            'Each delegate has been sent an email to set their password and access their delegate dashboard. Please ask them to check their inbox and spam folder.',
            'We look forward to welcoming your delegation to '.config('conference.name').'!',
        ]);
    }
}
