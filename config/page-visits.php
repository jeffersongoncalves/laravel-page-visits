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
        'livewire/update', 'admin/*', 'app/*', 'horizon/*', '_debugbar/*',
        'sw.js', 'manifest.json', 'favicon-proxy', 'up', 'og/*',
    ],

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
    | Documented placeholder for a future pruning command — not enforced by
    | this package yet.
    |
    */
    'retention_days' => env('PAGE_VISITS_RETENTION_DAYS', 400),

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
