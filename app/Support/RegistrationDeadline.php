<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The current registration deadline, e.g. "Extended deadline: 15–19 November", shown on the
 * home page and the registration form until it passes (Pakistan time).
 */
class RegistrationDeadline
{
    public static function label(): ?string
    {
        $deadline = config('conference.registration_deadline');

        if (! $deadline || empty($deadline['until'])) {
            return null;
        }

        if (now()->gt(Carbon::parse($deadline['until'], 'Asia/Karachi'))) {
            return null;
        }

        return __(':label: :dates', ['label' => __($deadline['label']), 'dates' => $deadline['dates']]);
    }
}
