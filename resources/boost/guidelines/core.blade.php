## Laravel Page Visits

Tracks page-view visits on any Laravel app's public GET routes: one row per hit, capturing
device/browser/OS, anonymized IP, GeoIP, locale, referer classification, and UTM parameters. Built
on top of `jeffersongoncalves/laravel-visitor-fingerprint` for every fingerprinting primitive — this
package owns only the `page_visits` table, the capture middleware, and the queued job.

### Installation

@verbatim
<code-snippet name="Install the package" lang="bash">
composer require jeffersongoncalves/laravel-page-visits
php artisan vendor:publish --tag="page-visits-config"
php artisan migrate
</code-snippet>
@endverbatim

### How it works

- `TrackPageVisit` is a **terminable** middleware, auto-appended to the `web` group when
  `page-visits.auto_register_middleware` is true. `handle()` returns immediately; `terminate()` runs
  after the response is sent and does the cheap inline capture (bot flag, fast device/OS, locale,
  geo headers snapshot) before dispatching `TrackPageVisitJob`.
- `TrackPageVisitJob` does the expensive work off the request thread: full user-agent parsing, IP
  hashing/anonymization, referer classification, and GeoIP resolution, then creates a `PageVisit`
  row.
- Non-GET requests and any path matching a glob in `page-visits.exclude` (e.g. `admin/*`,
  `livewire/update`) are skipped entirely — no job is dispatched.

### Configuration

@verbatim
<code-snippet name="Config example" lang="php">
// config/page-visits.php
return [
    'table' => 'page_visits',
    'track_ip_address' => true,
    'exclude' => ['admin/*', 'livewire/update'],
    'auto_register_middleware' => true,
    'compliance' => ['analytics_only' => false],
];
</code-snippet>
@endverbatim

Each `track_*` toggle nulls out its column instead of skipping the row entirely — the visit is
always recorded, just with less detail. `compliance.analytics_only` additionally nulls every
PII column (`ip_hash`, `ip_anonymized`, `ip_version`, `user_agent_hash`).

### GDPR / LGPD

@verbatim
<code-snippet name="Export or erase a visitor's data" lang="php">
use JeffersonGoncalves\LaravelPageVisits\Compliance\PageVisitPersonalDataService;

$service = new PageVisitPersonalDataService;

$service->exportForIp($ip);  // matching rows as arrays
$service->forgetForIp($ip);  // nulls PII columns in place
</code-snippet>
@endverbatim

### Best Practices

- Never add business logic to `TrackPageVisit::handle()` — it must stay a pass-through so it never
  delays the response. All capture work belongs in `terminate()` or the queued job.
- Any request-thread-local value (locale, live `$request`, geo headers) that the job needs must be
  snapshotted in `terminate()` — a queue worker has no request of its own.
