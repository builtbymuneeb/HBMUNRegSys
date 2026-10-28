<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts a visit to a public page. Bots, link previews, prefetches and logged-in
 * users are skipped, and a failure here never breaks the page.
 */
class RecordPageView
{
    private const BOTS = '~bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|discord|curl|wget|python|httpclient|headless|lighthouse|monitor|scan|okhttp|go-http|java/~i';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            try {
                $agent = (string) $request->userAgent();

                PageView::create([
                    'path' => '/'.ltrim($request->path(), '/'),
                    'visitor' => substr(hash_hmac('sha256', $request->ip().'|'.$agent, (string) config('app.key')), 0, 32),
                    'source' => $this->source($request),
                    'device' => $this->device($agent),
                    'browser' => $this->browser($agent),
                    'viewed_on' => now('Asia/Karachi')->toDateString(),
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        $agent = (string) $request->userAgent();
        $purpose = Str::lower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose')));

        return $request->isMethod('GET')
            && $response->getStatusCode() === 200
            && $request->user() === null
            && $agent !== ''
            && ! preg_match(self::BOTS, $agent)
            && ! Str::contains($purpose, ['prefetch', 'prerender']);
    }

    /**
     * Where the visitor came from: a ?utm_source or ?ref tag, else the referring site.
     * The WhatsApp and Instagram apps often send no referrer, so those show as direct.
     */
    private function source(Request $request): ?string
    {
        if (filled($tag = $request->query('utm_source') ?? $request->query('ref'))) {
            return Str::limit(Str::lower(trim((string) $tag)), 60, '');
        }

        $host = Str::lower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
        $own = Str::lower($request->getHost());

        if ($host === '' || $host === $own || Str::endsWith($host, '.'.$own)) {
            return null;
        }

        $host = Str::after($host, 'www.');

        return match (true) {
            Str::contains($host, ['whatsapp', 'wa.me']) => 'whatsapp',
            Str::contains($host, 'instagram') => 'instagram',
            Str::contains($host, 'facebook') || $host === 'fb.com' => 'facebook',
            Str::contains($host, 'google.') => 'google',
            Str::contains($host, ['linkedin', 'lnkd.in']) => 'linkedin',
            in_array($host, ['t.co', 'twitter.com', 'x.com'], true) => 'x / twitter',
            default => Str::limit($host, 60, ''),
        };
    }

    private function device(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet|android(?!.*mobile)/i', $agent) => 'tablet',
            (bool) preg_match('/mobi|iphone|ipod|android/i', $agent) => 'mobile',
            default => 'desktop',
        };
    }

    private function browser(string $agent): ?string
    {
        return match (true) {
            str_contains($agent, 'Instagram') => 'Instagram app',
            (bool) preg_match('/FBAN|FBAV|FB_IAB/', $agent) => 'Facebook app',
            str_contains($agent, 'Edg/') => 'Edge',
            (bool) preg_match('/OPR\/|Opera/', $agent) => 'Opera',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            (bool) preg_match('/Firefox|FxiOS/', $agent) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS/', $agent) => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            default => null,
        };
    }
}
