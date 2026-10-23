<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\DashboardUpdate;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;

/**
 * Sends a notification to every browser the user turned push notifications on in,
 * and forgets the browsers whose subscription has expired or been revoked.
 */
class WebPushChannel
{
    public function send(User $notifiable, DashboardUpdate $notification): void
    {
        $subscriptions = $notifiable->pushSubscriptions()->get();

        if ($subscriptions->isEmpty() || blank(config('services.webpush.private_key'))) {
            return;
        }

        $message = $notification->toWebPush($notifiable);
        $payload = json_encode($message, JSON_THROW_ON_ERROR);
        // The topic lets the push service replace an undelivered message with the same tag.
        $options = ['TTL' => 60 * 60 * 24, 'urgency' => 'normal', 'topic' => substr($message['tag'], 0, 32)];

        $webPush = app(WebPush::class);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification($subscription->toWebPush(), $payload, $options);
        }

        /** @var MessageSentReport $report */
        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            } elseif (! $report->isSuccess()) {
                Log::warning('Web push failed', ['user' => $notifiable->id, 'reason' => $report->getReason()]);
            }
        }
    }
}
