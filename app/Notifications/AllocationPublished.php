<?php

namespace App\Notifications;

use App\Models\Delegate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AllocationPublished extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Delegate $delegate) {}

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
        $committee = $this->delegate->allocatedCommittee;

        return (new MailMessage)
            ->subject('Your '.config('conference.name').' allocation')
            ->greeting("Hello {$this->delegate->name},")
            ->line('Your allocation is ready:')
            ->line("**Committee:** {$committee?->label()}")
            ->line('**'.($committee?->portfolioLabel() ?? __('Portfolio')).":** {$this->delegate->allocated_portfolio}")
            ->line('Log in to your delegate dashboard for study guides, announcements, your session link and your delegate card.')
            ->action('Open my dashboard', route('dashboard'));
    }
}
