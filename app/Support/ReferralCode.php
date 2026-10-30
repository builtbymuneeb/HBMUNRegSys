<?php

namespace App\Support;

use Illuminate\Support\Str;

class ReferralCode
{
    /**
     * Prefix for the codes handed to HB (Homebridge) ambassadors, e.g. HB01.
     */
    public const PREFIX = 'HB';

    /**
     * Prefix for the codes handed to Private ambassadors, e.g. PC01.
     */
    public const PRIVATE_PREFIX = 'PC';

    /**
     * Tidy a code for storage and display: upper case, no spaces.
     */
    public static function clean(?string $code): ?string
    {
        $code = Str::upper(preg_replace('/\s+/', '', (string) $code));

        return $code === '' ? null : $code;
    }

    /**
     * The key two codes are matched on, so "hb 1", "HB01" and "HB-001" all count as HB01.
     */
    public static function key(?string $code): ?string
    {
        $code = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));

        if ($code === '') {
            return null;
        }

        return preg_replace('/^([A-Z]*?)0*(\d+)$/', '$1$2', $code);
    }

    /**
     * Format a code number the way the system hands them out, e.g. 7 → HB07, 120 → HB120 (or PC07 with the Private prefix).
     */
    public static function format(int $number, string $prefix = self::PREFIX): string
    {
        return sprintf('%s%02d', $prefix, $number);
    }

    /**
     * The number in one of our own codes with this prefix (HB07 → 7), or null for any other code.
     */
    public static function number(string $code, string $prefix = self::PREFIX): ?int
    {
        return preg_match('/^'.$prefix.'-?0*(\d+)$/', Str::upper($code), $matches) ? (int) $matches[1] : null;
    }

    /**
     * Where a code sorts: HB codes by number, then PC codes by number, then any other code.
     *
     * @return array{int, int}
     */
    public static function sortKey(string $code): array
    {
        foreach ([self::PREFIX, self::PRIVATE_PREFIX] as $group => $prefix) {
            if (($number = self::number($code, $prefix)) !== null) {
                return [$group, $number];
            }
        }

        return [2, 0];
    }
}
