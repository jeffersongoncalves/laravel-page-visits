<div class="filament-hidden">

![Laravel Page Visits](https://raw.githubusercontent.com/jeffersongoncalves/laravel-page-visits/main/art/jeffersongoncalves-laravel-page-visits.png)

</div>

# Laravel Page Visits

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-page-visits.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-page-visits)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-page-visits/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/laravel-page-visits/actions?query=workflow%3ATests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-page-visits/pint.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/laravel-page-visits/actions?query=workflow%3A%22Fix+PHP+code+styling%22+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-page-visits.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-page-visits)
[![License](https://img.shields.io/packagist/l/jeffersongoncalves/laravel-page-visits.svg?style=flat-square)](LICENSE.md)

Tracks page-view visits on any Laravel app's public GET routes: one row per page hit, capturing
device/browser/GeoIP/locale/referer/UTM safely — anonymized IP, bot flagging, and GDPR export/
erasure — on top of [`jeffersongoncalves/laravel-visitor-fingerprint`](https://github.com/jeffersongoncalves/laravel-visitor-fingerprint)
for every fingerprinting primitive.

## Installation

You can install the package via composer:

```bash
composer require jeffersongoncalves/laravel-page-visits
```

Publish the config file and run the migration:

```bash
php artisan vendor:publish --tag="page-visits-config"
php artisan migrate
```

## Usage

Once installed, the package automatically appends `TrackPageVisit` to the `web` middleware group
(disable via `page-visits.auto_register_middleware`) and every GET request not matched by
`page-visits.exclude` gets a `page_visits` row — no further setup required.

```php
use JeffersonGoncalves\LaravelPageVisits\Models\PageVisit;

PageVisit::query()->latest('visited_at')->first();
```

### GDPR / LGPD export and erasure

```php
use JeffersonGoncalves\LaravelPageVisits\Compliance\PageVisitPersonalDataService;

$service = new PageVisitPersonalDataService;

$service->exportForIp($ip);  // matching rows as arrays
$service->forgetForIp($ip);  // nulls ip_hash/ip_anonymized/ip_version/user_agent_hash in place
```

### Daily aggregation and retention pruning

`page_visits` grows unbounded otherwise. Schedule `page-visits:aggregate-and-prune` to fold each
day's raw rows into `page_visit_daily_stats` and delete raw rows older than
`page-visits.retention_days`:

```php
// routes/console.php (Laravel 11+) or app/Console/Kernel.php
Schedule::command('page-visits:aggregate-and-prune')->dailyAt('01:00');
```

Run it for a specific day (e.g. to backfill) with `--date`:

```bash
php artisan page-visits:aggregate-and-prune --date=2026-09-01
```

`page_visit_daily_stats` holds one row per date with `visits_count`, `unique_visits_count`,
`bot_visits_count`, plus JSON breakdowns (`device_stats`, `browser_stats`, `os_stats`,
`country_stats`, `city_stats`, `referer_stats`, `referer_type_stats`, `utm_source_stats`,
`utm_medium_stats`, `utm_campaign_stats`, `language_stats`, `hourly_stats`) — query it directly via
`DB::table(config('page-visits.daily_stats_table'))` for "last 7/30/90 days" reporting instead of
scanning raw rows.

## Configuration

```php
// config/page-visits.php
return [
    'table' => 'page_visits',
    'daily_stats_table' => 'page_visit_daily_stats',

    'track_ip_address' => true,
    'track_browser' => true,
    'track_browser_version' => true,
    'track_operating_system' => true,
    'track_operating_system_version' => true,
    'track_device_type' => true,
    'track_referer_url' => true,
    'track_browser_language' => true,

    'exclude' => [
        'livewire/update', 'admin/*', 'app/*', 'horizon/*', '_debugbar/*',
        'sw.js', 'manifest.json', 'favicon-proxy', 'up', 'og/*',
    ],

    'auto_register_middleware' => true,

    'retention_days' => env('PAGE_VISITS_RETENTION_DAYS', 400),

    'compliance' => [
        'analytics_only' => env('PAGE_VISITS_ANALYTICS_ONLY', false),
    ],
];
```

Every `track_*` toggle nulls out its own column instead of skipping the row — the visit is always
recorded, just with less detail when a toggle is off. `compliance.analytics_only` additionally nulls
every PII column (`ip_hash`, `ip_anonymized`, `ip_version`, `user_agent_hash`).

GeoIP resolution, bot detection, IP anonymization, and referer classification are all delegated to
[`jeffersongoncalves/laravel-visitor-fingerprint`](https://github.com/jeffersongoncalves/laravel-visitor-fingerprint) —
see its README to configure the GeoIP driver (`headers`/`ip_api`/`maxmind`).

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email the author instead of using the issue tracker.

## Credits

- [Jefferson Gonçalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
