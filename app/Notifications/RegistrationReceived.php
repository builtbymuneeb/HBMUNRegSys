<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationReceived extends Notification implements ShouldQueue
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
            ->subject("Registration received ({$this->registration->reference})")
            ->greeting("Hello {$this->registration->contact_name},")
            ->line('Thank you for registering for '.config('conference.name').'. We have received your registration and payment screenshot.')
            ->line("Your reference number is **{$this->registration->reference}**.")
            ->line('Our registration team will verify your payment shortly. Once verified, each delegate will receive an email to set their password.')
            ->action('Check your status', route('registration.status'));
    }

    /**
     * The same message as plain text, for staff to send by WhatsApp.
     */
    public function toWhatsApp(): string
    {
        return implode("\n\n", [
            "Hello {$this->registration->contact_name},",
            'Thank you for registering for '.config('conference.name').". We have received your registration and payment screenshot. Your reference number is *{$this->registration->reference}*.",
            'Our registration team will verify your payment shortly. Once verified, each delegate will receive an email to set their password.',
            'Check your status: '.route('registration.status'),
        ]);
    }
}
