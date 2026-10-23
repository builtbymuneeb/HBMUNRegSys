<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A push notification about something new on the delegate dashboard. The tag matches the one the
 * open dashboard uses for the same update, so the browser shows a single notification for both.
 */
class DashboardUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $title, public string $body, public string $tag) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * The payload the service worker turns into a notification.
     *
     * @return array{title: string, body: string, tag: string, url: string}
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'tag' => $this->tag,
            'url' => route('dashboard', absolute: false),
        ];
    }
}
