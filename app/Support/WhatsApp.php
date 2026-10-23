<?php

namespace App\Support;

/**
 * Click-to-chat links (wa.me) that open WhatsApp on a number with a message already typed,
 * so staff can send it themselves. Nothing is sent from the server.
 */
class WhatsApp
{
    /**
     * The wa.me link for a phone number, or null if the number can't be dialled.
     */
    public static function link(?string $phone, ?string $message = null): ?string
    {
        $number = self::number($phone);

        if ($number === null) {
            return null;
        }

        return "https://wa.me/{$number}".(filled($message) ? '?text='.rawurlencode($message) : '');
    }

    /**
     * The number in international form without the plus, as wa.me expects.
     * Local Pakistani numbers (03XX XXXXXXX) get the 92 country code.
     */
    public static function number(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        $digits = match (true) {
            str_starts_with($digits, '0092') => substr($digits, 2),
            str_starts_with($digits, '03') && strlen($digits) === 11 => '92'.substr($digits, 1),
            str_starts_with($digits, '3') && strlen($digits) === 10 => '92'.$digits,
            default => $digits,
        };

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }
}
