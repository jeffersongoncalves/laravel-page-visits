<?php

namespace JeffersonGoncalves\LaravelPageVisits\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JeffersonGoncalves\LaravelPageVisits\Jobs\TrackPageVisitJob;
use JeffersonGoncalves\VisitorFingerprint\GeoIp\HeadersGeoIpDriver;
use JeffersonGoncalves\VisitorFingerprint\Support\AcceptLanguage;
use JeffersonGoncalves\VisitorFingerprint\Support\BotDetector;
use JeffersonGoncalves\VisitorFingerprint\Support\UserAgentParser;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Terminable middleware: handle() returns immediately so the response is
 * never delayed, and Laravel calls terminate() afterwards (after the
 * response has been sent) to do the actual capture.
 *
 * Only the cheap, request-thread-local values are read here (bot flag, fast
 * device/OS, browser language, locale, geo headers) — full UA parsing, IP
 * hashing and GeoIP resolution happen off-thread in TrackPageVisitJob.
 */
class TrackPageVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->isMethod('GET')
            || $this->isExcluded($request->path())
            || $this->isExcludedRoute($request)
            || $this->isSkippedStatus($response->getStatusCode())) {
            return;
        }

        $userAgent = (string) $request->userAgent();
        // Every real browser sends Accept-Language; scripted clients behind
        // residential proxies often don't, and pass BotDetector otherwise.
        $isBot = BotDetector::isBot($userAgent)
            || (config('page-visits.require_accept_language', true) && blank($request->headers->get('accept-language')))
            || (config('page-visits.flag_forged_search_referer', true) && $this->hasForgedSearchReferer($request));

        if ($isBot && ! config('page-visits.store_bots', true)) {
            return;
        }

        try {
            TrackPageVisitJob::dispatch([
                'path' => $request->path(),
                'route_name' => $request->route()->getName(),
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
                'ip' => (string) $request->ip(),
                // Snapshotted here (request-thread-local) because a queue
                // worker has no live request/app-locale of its own.
                'geo_headers' => HeadersGeoIpDriver::snapshot($request),
                'user_agent' => $userAgent,
                'referer_url' => $request->headers->get('referer'),
                'browser_language' => AcceptLanguage::preferred($request->headers->get('accept-language')),
                'locale' => app()->getLocale(),
                'app_host' => (string) $request->getHost(),
                'is_bot' => $isBot,
                'device_type' => UserAgentParser::fastDeviceType($userAgent),
                'operating_system' => UserAgentParser::fastOperatingSystem($userAgent),
                'utm_source' => $request->query('utm_source'),
                'utm_medium' => $request->query('utm_medium'),
                'utm_campaign' => $request->query('utm_campaign'),
                'utm_term' => $request->query('utm_term'),
                'utm_content' => $request->query('utm_content'),
                'response_time_ms' => $this->responseTimeMs($request),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function isExcluded(string $path): bool
    {
        return Str::is(config('page-visits.exclude', []), $path);
    }

    /**
     * Route-name exclusion, for routes whose path can't be globbed (e.g. a
     * short-link redirect fallback route matching an arbitrary key at the
     * root) but whose name is stable and known.
     */
    protected function isExcludedRoute(Request $request): bool
    {
        $routeName = $request->route()->getName();

        return $routeName !== null && Str::is(config('page-visits.exclude_route_names', []), $routeName);
    }

    /**
     * Exact codes (404) or "x" wildcard ranges ('4xx', '50x').
     */
    protected function isSkippedStatus(int $status): bool
    {
        $patterns = array_map(
            // ponytail: Str::is only knows "*"; fine since status codes are always 3 digits.
            fn ($code) => str_ireplace('x', '*', (string) $code),
            config('page-visits.skip_status_codes', []),
        );

        return $patterns !== [] && Str::is($patterns, (string) $status);
    }

    /**
     * Crawlers forge a search referer from an engine name plus the app's own
     * suffix (app on example.dev.br sends www.google.dev.br) — a host that
     * doesn't exist.
     */
    protected function hasForgedSearchReferer(Request $request): bool
    {
        $appHost = strtolower($request->getHost());
        $suffix = Str::after($appHost, '.');

        // ponytail: real engine domains end in "com", a ccTLD or "com|co.<cc>"
        // (google.com.br, yahoo.co.jp); a suffix shaped like that is never
        // flagged, so a forged google.com.br on a .com.br site still passes.
        // Swap for a real engine-domain list if that gap ever shows up.
        if ($suffix === $appHost || preg_match('/^((com|co)\.)?[a-z]{2,3}$/', $suffix)) {
            return false;
        }

        $refererHost = strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));

        return preg_match('/^(www\.)?(google|bing|yahoo|duckduckgo|baidu|yandex)\.'.preg_quote($suffix, '/').'$/', $refererHost) === 1;
    }

    protected function responseTimeMs(Request $request): ?int
    {
        $startedAt = $request->server('REQUEST_TIME_FLOAT');

        return is_numeric($startedAt) ? (int) ((microtime(true) - (float) $startedAt) * 1000) : null;
    }
}
