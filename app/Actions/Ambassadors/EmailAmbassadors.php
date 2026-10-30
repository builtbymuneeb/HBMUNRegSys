<?php

namespace App\Actions\Ambassadors;

use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use App\Models\User;
use App\Notifications\AmbassadorMessage;
use App\Notifications\AmbassadorWelcome;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Queues the welcome email, or a message staff wrote, to campus ambassadors.
 * Anyone without an email address is left out.
 */
class EmailAmbassadors
{
    /**
     * @param  iterable<CampusAmbassador>  $ambassadors
     * @return int How many emails were queued.
     */
    public function welcome(iterable $ambassadors, User $by): int
    {
        return $this->send($ambassadors, $by, 'ambassador_welcome_emailed', function (CampusAmbassador $ambassador) {
            Notification::route('mail', $ambassador->email)->notify(new AmbassadorWelcome($ambassador));
            $ambassador->forceFill(['welcome_emailed_at' => now()])->saveQuietly();
        });
    }

    /**
     * @param  iterable<CampusAmbassador>  $ambassadors
     * @return int How many emails were queued.
     */
    public function message(iterable $ambassadors, string $subject, string $body, User $by): int
    {
        return $this->send($ambassadors, $by, 'ambassador_emailed', function (CampusAmbassador $ambassador) use ($subject, $body) {
            Notification::route('mail', $ambassador->email)->notify(new AmbassadorMessage($ambassador, $subject, $body));
        }, ['subject' => $subject]);
    }

    /**
     * @param  iterable<CampusAmbassador>  $ambassadors
     * @param  callable(CampusAmbassador): void  $notify
     * @param  array<string, mixed>  $properties
     */
    private function send(iterable $ambassadors, User $by, string $action, callable $notify, array $properties = []): int
    {
        $recipients = Collection::make($ambassadors)->filter(fn (CampusAmbassador $ambassador) => filled($ambassador->email));

        foreach ($recipients as $ambassador) {
            $notify($ambassador);
            AuditLog::record($action, $ambassador, $by, ['code' => $ambassador->code, ...$properties]);
        }

        return $recipients->count();
    }
}
