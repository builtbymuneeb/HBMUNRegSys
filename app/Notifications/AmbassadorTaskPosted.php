<?php

namespace App\Notifications;

use App\Models\AmbassadorTask;
use App\Models\CampusAmbassador;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells an ambassador a new task is waiting in their portal.
 */
class AmbassadorTaskPosted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AmbassadorTask $task, public CampusAmbassador $ambassador) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New task: {$this->task->title} ({$this->task->points} points)")
            ->greeting("Hello {$this->ambassador->firstName()},")
            ->line("There is a new task in your Campus Ambassador portal, worth **{$this->task->points} points**".($this->task->repeatable ? ' each time' : '').'.')
            ->line("**{$this->task->title}**")
            ->lineIf(filled($this->task->description), Str::limit((string) $this->task->description, 400))
            ->lineIf($this->task->due_at !== null, 'Due by '.$this->task->due_at?->timezone('Asia/Karachi')->format('j M, g:i a').'.')
            ->action('Open the task', route('ambassador.tasks.show', $this->task));
    }
}
