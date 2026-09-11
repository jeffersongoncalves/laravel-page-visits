<?php

namespace JeffersonGoncalves\LaravelPageVisits\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JeffersonGoncalves\LaravelPageVisits\Models\PageVisit;
use JeffersonGoncalves\VisitorFingerprint\Contracts\GeoIpDriver;
use JeffersonGoncalves\VisitorFingerprint\Data\GeoLocation;
use JeffersonGoncalves\VisitorFingerprint\GeoIp\HeadersGeoIpDriver;
use JeffersonGoncalves\VisitorFingerprint\Support\IpAnonymizer;
use JeffersonGoncalves\VisitorFingerprint\Support\RefererClassifier;
use JeffersonGoncalves\VisitorFingerprint\Support\UserAgentParser;
use Throwable;

/**
 * Turns the raw payload captured on the request thread (see
 * TrackPageVisit::terminate()) into a stored PageVisit row. Any failure here
 * must never surface past this job — a tracking bug can't be allowed to
 * retry-storm or alert on a working page load.
 *
 * @phpstan-type Payload array{
 *     path: string, route_name: ?string, method: string, status_code: ?int,
 *     ip: string, geo_headers: array<string, string|null>, user_agent: string,
 *     referer_url: ?string, browser_language: ?string, locale: ?string,
 *     app_host: string, is_bot: bool, device_type: ?string,
 *     operating_system: ?string, utm_source: ?string, utm_medium: ?string,
 *     utm_campaign: ?string, utm_term: ?string, utm_content: ?string,
 *     response_time_ms: ?int,
 * }
 */
class TrackPageVisitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  Payload  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(GeoIpDriver $geoIp): void
    {
        try {
            $this->track($geoIp);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function track(GeoIpDriver $geoIp): void
    {
        $payload = $this->payload;
        $ip = $payload['ip'];

        $ipAttributes = $this->ipAttributes($payload);
        $geo = $ip !== '' ? $this->resolveGeo($geoIp, $ip, $payload['geo_headers']) : new GeoLocation;
        $ua = $this->userAgentAttributes($payload);
        $referer = $this->refererAttributes($payload);

        PageVisit::query()->create(array_merge($ipAttributes, $ua, $referer, [
            'visited_at' => now(),
            'path' => $payload['path'],
            'route_name' => $payload['route_name'],
            'method' => $payload['method'],
            'status_code' => $payload['status_code'],
            'country' => $geo->country,
            'country_code' => $geo->countryCode,
            'region' => $geo->region,
            'city' => $geo->city,
            'latitude' => $geo->latitude,
            'longitude' => $geo->longitude,
            'timezone' => $geo->timezone,
            'isp' => $geo->isp,
            'asn' => $geo->asn,
            'browser_language' => config('page-visits.track_browser_language', true) ? $payload['browser_language'] : null,
            'locale' => $payload['locale'],
            'user_agent_hash' => config('page-visits.compliance.analytics_only', false) ? null : hash('sha256', $payload['user_agent']),
            'utm_source' => $payload['utm_source'],
            'utm_medium' => $payload['utm_medium'],
            'utm_campaign' => $payload['utm_campaign'],
            'utm_term' => $payload['utm_term'],
            'utm_content' => $payload['utm_content'],
            'is_bot' => $payload['is_bot'],
            'is_vpn' => false,
            'is_proxy' => false,
            'is_tor' => false,
            'is_datacenter' => false,
            'response_time_ms' => $payload['response_time_ms'],
            'created_at' => now(),
        ]));
    }

    /**
     * @param  Payload  $payload
     * @return array{ip_hash: ?string, ip_anonymized: ?string, ip_version: ?int}
     */
    protected function ipAttributes(array $payload): array
    {
        $analyticsOnly = (bool) config('page-visits.compliance.analytics_only', false);

        if (! config('page-visits.track_ip_address', true) || $payload['ip'] === '' || $analyticsOnly) {
            return ['ip_hash' => null, 'ip_anonymized' => null, 'ip_version' => null];
        }

        return [
            'ip_hash' => IpAnonymizer::hash($payload['ip']),
            'ip_anonymized' => IpAnonymizer::truncate($payload['ip']),
            'ip_version' => IpAnonymizer::version($payload['ip']),
        ];
    }

    /**
     * @param  Payload  $payload
     * @return array{device_type: ?string, browser: ?string, browser_version: ?string, operating_system: ?string, operating_system_version: ?string}
     */
    protected function userAgentAttributes(array $payload): array
    {
        $parsed = UserAgentParser::parse($payload['user_agent']);

        return [
            'device_type' => config('page-visits.track_device_type', true) ? $payload['device_type'] : null,
            'browser' => config('page-visits.track_browser', true) ? $parsed['browser'] : null,
            'browser_version' => config('page-visits.track_browser_version', true) ? $parsed['browser_version'] : null,
            'operating_system' => config('page-visits.track_operating_system', true) ? $payload['operating_system'] : null,
            'operating_system_version' => config('page-visits.track_operating_system_version', true) ? $parsed['operating_system_version'] : null,
        ];
    }

    /**
     * @param  Payload  $payload
     * @return array{referer_url: ?string, referer_host: ?string, referer_type: ?string}
     */
    protected function refererAttributes(array $payload): array
    {
        if (! config('page-visits.track_referer_url', true)) {
            return ['referer_url' => null, 'referer_host' => null, 'referer_type' => null];
        }

        return [
            'referer_url' => $payload['referer_url'],
            'referer_host' => $payload['referer_url'] ? parse_url($payload['referer_url'], PHP_URL_HOST) : null,
            'referer_type' => RefererClassifier::classify($payload['referer_url'], $payload['app_host']),
        ];
    }

    /**
     * @param  array<string, string|null>  $geoHeaders
     */
    protected function resolveGeo(GeoIpDriver $geoIp, string $ip, array $geoHeaders): GeoLocation
    {
        try {
            // HeadersGeoIpDriver needs the header snapshot instead of the
            // (nonexistent, on a worker) live request.
            return $geoIp instanceof HeadersGeoIpDriver
                ? $geoIp->resolveFromHeaders($geoHeaders)
                : $geoIp->resolve($ip);
        } catch (Throwable $e) {
            report($e);

            return new GeoLocation;
        }
    }
}
