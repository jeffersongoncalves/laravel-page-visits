<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    |
    | Name of the table this package writes page visits to.
    |
    */
    'table' => 'page_visits',

    /*
    |--------------------------------------------------------------------------
    | Daily Stats Table
    |--------------------------------------------------------------------------
    |
    | Name of the table page-visits:aggregate-and-prune folds each day's raw
    | rows into before pruning them.
    |
    */
    'daily_stats_table' => 'page_visit_daily_stats',

    /*
    |--------------------------------------------------------------------------
    | Field Tracking
    |--------------------------------------------------------------------------
    |
    | Per-field toggles read by TrackPageVisitJob. When a toggle is off the
    | corresponding column is stored as null instead of the captured value —
    | the visit row itself is still created either way.
    |
    */
    'track_ip_address' => true,
    'track_browser' => true,
    'track_browser_version' => true,
    'track_operating_system' => true,
    'track_operating_system_version' => true,
    'track_device_type' => true,
    'track_referer_url' => true,
    'track_browser_language' => true,

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | Glob patterns (matched via Str::is()) that TrackPageVisit skips —
    | app-internal, admin/panel, and infrastructure routes that aren't real
    | page views.
    |
    */
    'exclude' => [
        // Bare segment AND "/*" for each — Str::is('horizon/*', 'horizon')
        // is false, so the no-trailing-slash root of a sub-app (e.g. the
        // Horizon dashboard's own index) needs its own exact entry too.
        'livewire/update',
        'admin', 'admin/*',
        'app', 'app/*',
        'horizon', 'horizon/*',
        '_debugbar', '_debugbar/*',
        'sw.js', 'manifest.json', 'favicon-proxy', 'up',
        'og', 'og/*',
        'sitemap.xml', 'llms.txt', 'robots.txt',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Route Names
    |--------------------------------------------------------------------------
    |
    | Glob patterns (matched via Str::is()) against the current route's name
    | — for routes a path glob can't reliably target, e.g. a short-link
    | redirect fallback route that matches an arbitrary key at the root
    | (jeffersongoncalves/laravel-short-url's "short-url.redirect"). Empty
    | by default — this package doesn't know what other packages an app has
    | installed, so add app-specific route names here after publishing this
    | config.
    |
    */
    'exclude_route_names' => [],

    /*
    |--------------------------------------------------------------------------
    | Auto-Register Middleware
    |--------------------------------------------------------------------------
    |
    | When true, TrackPageVisit is appended to the "web" middleware group
    | automatically. Disable to register it manually (e.g. on a subset of
    | routes only).
    |
    */
    'auto_register_middleware' => true,

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Raw visit rows older than this are pruned by page-visits:aggregate-
    | and-prune after being folded into daily_stats_table.
    |
    */
    'retention_days' => env('PAGE_VISITS_RETENTION_DAYS', 400),

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    |
    | This package self-registers page-visits:aggregate-and-prune on the
    | schedule once the app boots — no host-side Schedule::command() needed.
    | Set enabled to false to opt out (e.g. to run it yourself with
    | different args, or on a queue worker only).
    |
    */
    'scheduling' => [
        'aggregate_and_prune' => [
            'enabled' => env('PAGE_VISITS_SCHEDULE_AGGREGATE_AND_PRUNE', true),
            'time' => env('PAGE_VISITS_SCHEDULE_AGGREGATE_AND_PRUNE_TIME', '02:30'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance (LGPD/GDPR)
    |--------------------------------------------------------------------------
    |
    | - analytics_only: when true, no personally-identifiable fields
    |   (ip_hash, ip_anonymized, ip_version, user_agent_hash) are stored on
    |   the visit row.
    |
    */
    'compliance' => [
        'analytics_only' => env('PAGE_VISITS_ANALYTICS_ONLY', false),
    ],

];
