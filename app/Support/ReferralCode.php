<?php

namespace App\Support;

use Illuminate\Support\Str;

class ReferralCode
{
    /**
     * Prefix for codes the system hands out, e.g. HB-001.
     */
    public const PREFIX = 'HB';

    /**
     * Tidy a code for storage and display: upper case, no spaces.
     */
    public static function clean(?string $code): ?string
    {
        $code = Str::upper(preg_replace('/\s+/', '', (string) $code));

        return $code === '' ? null : $code;
    }

    /**
     * The key two codes are matched on, so "hb 1", "HB001" and "HB-001" all count as HB-001.
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
     * Format a code number the way the system hands them out, e.g. 7 → HB-007.
     */
    public static function format(int $number): string
    {
        return sprintf('%s-%03d', self::PREFIX, $number);
    }

    /**
     * The number in one of our own codes (HB-007 → 7), or null for any other code.
     */
    public static function number(string $code): ?int
    {
        return preg_match('/^'.self::PREFIX.'-?0*(\d+)$/', Str::upper($code), $matches) ? (int) $matches[1] : null;
    }
}
