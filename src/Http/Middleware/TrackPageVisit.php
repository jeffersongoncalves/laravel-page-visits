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
        if (! $request->isMethod('GET') || $this->isExcluded($request->path())) {
            return;
        }

        $userAgent = (string) $request->userAgent();

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
                'is_bot' => BotDetector::isBot($userAgent),
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

    protected function responseTimeMs(Request $request): ?int
    {
        $startedAt = $request->server('REQUEST_TIME_FLOAT');

        return is_numeric($startedAt) ? (int) ((microtime(true) - (float) $startedAt) * 1000) : null;
    }
}
