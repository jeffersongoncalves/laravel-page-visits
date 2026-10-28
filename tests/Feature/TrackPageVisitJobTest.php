<?php

use JeffersonGoncalves\LaravelPageVisits\Jobs\TrackPageVisitJob;
use JeffersonGoncalves\LaravelPageVisits\Models\PageVisit;
use JeffersonGoncalves\VisitorFingerprint\Contracts\GeoIpDriver;
use JeffersonGoncalves\VisitorFingerprint\Data\GeoLocation;

/**
 * @return array<string, mixed>
 */
function pageVisitPayload(array $overrides = []): array
{
    return array_merge([
        'path' => 'blog/hello-world',
        'route_name' => 'blog.show',
        'method' => 'GET',
        'status_code' => 200,
        'ip' => '203.0.113.10',
        'geo_headers' => [],
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0.0.0 Safari/537.36',
        'referer_url' => 'https://www.google.com/search?q=hello',
        'browser_language' => 'en-US',
        'locale' => 'en',
        'app_host' => 'example.test',
        'is_bot' => false,
        'device_type' => 'desktop',
        'operating_system' => 'Windows',
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
        'utm_campaign' => 'launch',
        'utm_term' => null,
        'utm_content' => null,
        'response_time_ms' => 42,
    ], $overrides);
}

it('creates a page visit row with the expected columns populated', function () {
    (new TrackPageVisitJob(pageVisitPayload()))->handle(app(GeoIpDriver::class));

    expect(PageVisit::query()->count())->toBe(1);

    $visit = PageVisit::query()->first();

    expect($visit->path)->toBe('blog/hello-world')
        ->and($visit->route_name)->toBe('blog.show')
        ->and($visit->method)->toBe('GET')
        ->and($visit->status_code)->toBe(200)
        ->and($visit->ip_hash)->not->toBeNull()
        ->and($visit->device_type)->toBe('desktop')
        ->and($visit->browser)->toBe('Chrome')
        ->and($visit->operating_system)->toBe('Windows')
        ->and($visit->referer_type)->toBe('search')
        ->and($visit->locale)->toBe('en')
        ->and($visit->utm_source)->toBe('newsletter')
        ->and($visit->is_bot)->toBeFalse()
        ->and($visit->response_time_ms)->toBe(42);
});

it('nulls out a column when its track_* config toggle is off', function () {
    config()->set('page-visits.track_browser', false);
    config()->set('page-visits.track_device_type', false);

    (new TrackPageVisitJob(pageVisitPayload()))->handle(app(GeoIpDriver::class));

    $visit = PageVisit::query()->first();

    expect($visit->browser)->toBeNull()
        ->and($visit->device_type)->toBeNull()
        // Untouched toggles still capture their value.
        ->and($visit->operating_system)->toBe('Windows');
});

it('clamps oversized request-derived strings to their column size', function () {
    (new TrackPageVisitJob(pageVisitPayload([
        'path' => str_repeat('é', 300),
        'referer_url' => 'https://example.com/'.str_repeat('a', 3000),
        'utm_source' => str_repeat('x', 300),
        'utm_medium' => ['array'],
    ])))->handle(app(GeoIpDriver::class));

    $visit = PageVisit::query()->first();

    expect($visit->path)->toBe(str_repeat('é', 255))
        ->and(mb_strlen($visit->referer_url))->toBe(2048)
        ->and($visit->utm_source)->toBe(str_repeat('x', 255))
        ->and($visit->utm_medium)->toBeNull();
});

function geoIpWithAsn(?string $asn): GeoIpDriver
{
    return new class($asn) implements GeoIpDriver
    {
        public function __construct(private ?string $asn) {}

        public function resolve(string $ip): GeoLocation
        {
            return new GeoLocation(asn: $this->asn);
        }
    };
}

it('flags is_datacenter when the ASN is in datacenter_asns', function (?string $asn, bool $expected) {
    config()->set('page-visits.datacenter_asns', ['AS132203', '14618']);

    (new TrackPageVisitJob(pageVisitPayload()))->handle(geoIpWithAsn($asn));

    expect(PageVisit::query()->first()->is_datacenter)->toBe($expected);
})->with([
    'listed, with org name' => ['AS132203 Tencent Building, Kejizhongyi Avenue', true],
    'listed without AS prefix in config' => ['AS14618 Amazon.com, Inc.', true],
    'prefix of a listed ASN' => ['AS1320 Some ISP', false],
    'residential ISP' => ['AS28573 Claro S.A.', false],
    'unresolved' => [null, false],
]);

it('skips datacenter visits when store_datacenter is off', function () {
    config()->set('page-visits.datacenter_asns', ['AS132203']);
    config()->set('page-visits.store_datacenter', false);

    (new TrackPageVisitJob(pageVisitPayload()))->handle(geoIpWithAsn('AS132203 Tencent'));
    (new TrackPageVisitJob(pageVisitPayload()))->handle(geoIpWithAsn('AS28573 Claro S.A.'));

    expect(PageVisit::query()->pluck('asn')->all())->toBe(['AS28573 Claro S.A.']);
});

it('nulls out PII columns when compliance.analytics_only is enabled', function () {
    config()->set('page-visits.compliance.analytics_only', true);

    (new TrackPageVisitJob(pageVisitPayload()))->handle(app(GeoIpDriver::class));

    $visit = PageVisit::query()->first();

    expect($visit->ip_hash)->toBeNull()
        ->and($visit->ip_anonymized)->toBeNull()
        ->and($visit->ip_version)->toBeNull()
        ->and($visit->user_agent_hash)->toBeNull();
});
