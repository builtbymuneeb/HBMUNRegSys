<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Builds the "Stuck? Message us on WhatsApp" link shown when someone hits a problem
 * (expired link, failed login, wrong account...). The message is pre-filled with the
 * problem, the exact error and enough detail for the team to help without asking twice.
 * Secrets never go in it: reset tokens are cut out of the page address.
 */
class HelpWhatsApp
{
    public const DEFAULT_NUMBER = '923085582124';

    /**
     * @param  array<string, string|null>  $details  Extra lines, e.g. ['Email' => '...'].
     */
    public static function url(string $problem, ?string $error = null, array $details = [], ?Request $request = null): string
    {
        $number = preg_replace('/\D/', '', (string) config('conference.support_whatsapp', self::DEFAULT_NUMBER));

        return 'https://wa.me/'.$number.'?text='.rawurlencode(self::message($problem, $error, $details, $request));
    }

    /**
     * @param  array<string, string|null>  $details
     */
    public static function message(string $problem, ?string $error = null, array $details = [], ?Request $request = null): string
    {
        $request ??= request();
        $user = $request->user();

        $lines = array_filter([
            'Email' => null,
            ...$details,
            'Logged in as' => $user ? "{$user->name} ({$user->email})" : null,
            'Page' => self::safePage($request),
            'Time' => now('Asia/Karachi')->format('j M Y, g:i a').' (PKT)',
            'Device' => self::device((string) $request->userAgent()),
        ], fn ($value) => filled($value));

        $text = "Hi HBMUN team, I'm stuck on the website and need help.\n\n";
        $text .= "*Problem:* {$problem}\n";

        if (filled($error)) {
            $text .= "*Error shown:* {$error}\n";
        }

        foreach ($lines as $label => $value) {
            $text .= "*{$label}:* {$value}\n";
        }

        return rtrim($text);
    }

    /**
     * The page address without the query string or any reset token.
     */
    private static function safePage(Request $request): string
    {
        $path = preg_replace('#/reset-password/[^/?]+#', '/reset-password/(link)', '/'.ltrim($request->path(), '/'));

        return $request->getSchemeAndHttpHost().$path;
    }

    private static function device(string $agent): string
    {
        $os = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iPhone/iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'Mac',
            default => 'Other',
        };

        $browser = match (true) {
            str_contains($agent, 'com.google.android.gm') || str_contains($agent, 'GSA/') => 'Gmail/Google app browser',
            str_contains($agent, 'Instagram') => 'Instagram browser',
            str_contains($agent, 'FBAN') || str_contains($agent, 'FBAV') => 'Facebook browser',
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            str_contains($agent, 'Firefox/') => 'Firefox',
            default => 'Browser',
        };

        return "{$os}, {$browser}";
    }
}
