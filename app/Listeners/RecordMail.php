<?php

namespace App\Listeners;

use App\Models\MailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Throwable;

class RecordMail
{
    /**
     * Log each recipient of a message the mail server accepted.
     */
    public function sent(MessageSent $event): void
    {
        $message = $event->message;

        foreach ([...$message->getTo(), ...$message->getCc(), ...$message->getBcc()] as $address) {
            MailLog::create([
                'status' => MailLog::SENT,
                'to' => $address->getAddress(),
                'subject' => Str::limit((string) $message->getSubject(), 250),
                'notification' => $event->data['__laravel_notification'] ?? null,
            ]);
        }
    }

    /**
     * Log the recipients of a queued email notification that gave up after its last retry.
     */
    public function failed(JobFailed $event): void
    {
        try {
            $command = unserialize($event->job->payload()['data']['command'] ?? '');
        } catch (Throwable) {
            return;
        }

        if (! $command instanceof SendQueuedNotifications || ! in_array('mail', (array) $command->channels, true)) {
            return;
        }

        foreach ($command->notifiables as $notifiable) {
            $route = $notifiable instanceof AnonymousNotifiable
                ? ($notifiable->routes['mail'] ?? null)
                : $notifiable->routeNotificationFor('mail', $command->notification);

            foreach ((array) $route as $key => $value) {
                $email = $value instanceof Address ? $value->getAddress() : (is_string($key) ? $key : $value);

                MailLog::create([
                    'status' => MailLog::FAILED,
                    'to' => (string) $email,
                    'notification' => $command->notification::class,
                    'error' => Str::limit($event->exception->getMessage(), 1000),
                ]);
            }
        }
    }
}
