<?php

namespace App\Notifications;

use App\Enums\SubmissionStatus;
use App\Models\AmbassadorSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lets an ambassador know staff approved a task they marked done, or sent it back.
 */
class AmbassadorSubmissionReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AmbassadorSubmission $submission) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->submission->task;
        $mail = (new MailMessage)->greeting("Hello {$this->submission->ambassador->firstName()},");

        if ($this->submission->status === SubmissionStatus::Approved) {
            return $mail
                ->subject("+{$this->submission->points_awarded} points: {$task->title}")
                ->line("Staff approved **{$task->title}**. You earned **{$this->submission->points_awarded} points**.")
                ->lineIf(filled($this->submission->review_note), 'Note from staff: '.$this->submission->review_note)
                ->action('See the leaderboard', route('ambassador.leaderboard'));
        }

        return $mail
            ->subject("Please check: {$task->title}")
            ->line("Staff sent back **{$task->title}**.")
            ->line('Reason: '.($this->submission->review_note ?: 'No reason given.'))
            ->lineIf($task->isOpen(), 'You can fix it and mark it as done again from your portal.')
            ->action('Open the task', route('ambassador.tasks.show', $task));
    }
}
