<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Tonight's free-registration offer: registrations submitted between 6 pm and midnight
 * (Pakistan time) on 17 November 2026 need no payment. Everything about it switches
 * itself off at midnight. The times can be moved with conference.free_registration.*.
 */
class FreeRegistrationOffer
{
    public const TIMEZONE = 'Asia/Karachi';

    public static function startsAt(): Carbon
    {
        return Carbon::parse(config('conference.free_registration.starts_at', '2026-11-17 18:00'), self::TIMEZONE);
    }

    public static function endsAt(): Carbon
    {
        return Carbon::parse(config('conference.free_registration.ends_at', '2026-11-18 00:00'), self::TIMEZONE);
    }

    public static function isActive(): bool
    {
        return now()->between(self::startsAt(), self::endsAt()) && now()->lt(self::endsAt());
    }

    /**
     * "12:00 am tonight", for banners.
     */
    public static function endsLabel(): string
    {
        return self::endsAt()->format('g:i a').' '.__('tonight (PKT)');
    }
}
