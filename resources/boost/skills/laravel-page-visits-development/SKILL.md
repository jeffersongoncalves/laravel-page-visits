---
name: laravel-page-visits-development
description: Build and work with Laravel Page Visits features — page-view capture middleware, the tracking job, and GDPR/LGPD compliance helpers.
---

# Laravel Page Visits Development

## When to use this skill

Use this skill when:
- Adding or changing a column captured on the `page_visits` table
- Working on `TrackPageVisit` (the terminable middleware) or `TrackPageVisitJob`
- Wiring up `PageVisitPersonalDataService` for a data-subject request

## Core Concepts

### Terminable middleware, not a pipeline stage

`TrackPageVisit::handle()` only calls `$next($request)` — nothing else. All capture logic lives in
`terminate(Request $request, Response $response)`, which Laravel invokes automatically after the
response has already been sent to the client (`FastCgiHandler`/PHP-FPM) or immediately after
(everything else). This is what makes tracking free from the visitor's point of view.

```php
class TrackPageVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // capture + dispatch here
    }
}
```

### Fast path vs. slow path

`terminate()` only does what's cheap enough to run inline: `BotDetector::isBot()`,
`UserAgentParser::fastDeviceType()/fastOperatingSystem()`, `AcceptLanguage::preferred()`, and
`app()->getLocale()`. Everything expensive (full UA parse, IP hash, GeoIP HTTP/DB lookup, referer
classification) happens inside `TrackPageVisitJob::handle()`, off the request thread.

### Snapshot request-thread-local values before dispatching

A queued job may run on a worker process with no `$request` and no request-scoped app state. Any
value that only exists on the request thread — `app()->getLocale()`, the live `Request` object, geo
headers — must be read and put into the job's `payload` array **inside `terminate()`**, not inside
the job:

```php
'locale' => app()->getLocale(),
'geo_headers' => HeadersGeoIpDriver::snapshot($request),
```

### Every `track_*` toggle nulls a column, never skips the row

`TrackPageVisitJob` always creates a `PageVisit` row. Each `page-visits.track_*` config key only
controls whether its own column is populated or stored as `null` — see
`TrackPageVisitJob::userAgentAttributes()`/`ipAttributes()`/`refererAttributes()` for the pattern to
follow when adding a new trackable field.

## Common Patterns

### Adding a new capturable field

1. Add the column to `database/migrations/create_page_visits_table.php.stub` and the `@property`
   docblock + `casts()` on `PageVisit` if it isn't a plain string.
2. Capture the cheap/raw value in `TrackPageVisit::terminate()`'s payload array.
3. Resolve/transform it inside `TrackPageVisitJob::track()` (add a `track_*` toggle in
   `config/page-visits.php` first if the field is PII-adjacent).

### Excluding a route from tracking

Add a glob pattern to `page-visits.exclude` — matched via `Str::is()` against `$request->path()`
(no leading slash). No code change needed.

## Troubleshooting

### A test route isn't producing a `PageVisit` row

Check that the request used `->middleware('web')` (or otherwise a group that includes
`TrackPageVisit`) and that `page-visits.auto_register_middleware` wasn't disabled in your test's
`getEnvironmentSetUp()`. Also confirm the path isn't matched by `page-visits.exclude`.

### GeoIP fields are always null

`HeadersGeoIpDriver` (the default) reads CDN-injected headers (Cloudflare/CloudFront) — locally,
with no CDN in front of the app, every geo field will be null. That's expected; switch
`visitor-fingerprint.geoip.driver` to `ip_api` or `maxmind` to resolve geo without a CDN.

## API Reference

### `PageVisitPersonalDataService::exportForIp(string $ip): array`

Returns every `page_visits` row whose `ip_hash` matches `IpAnonymizer::hash($ip)`, as arrays.

### `PageVisitPersonalDataService::forgetForIp(string $ip): int`

Nulls `ip_hash`, `ip_anonymized`, `ip_version`, `user_agent_hash` on matching rows in place (rows are
kept for aggregate accuracy). Returns the number of rows updated.
