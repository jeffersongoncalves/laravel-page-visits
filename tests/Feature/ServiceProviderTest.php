<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

it('registers the config file', function () {
    expect(config('page-visits.table'))->toBe('page_visits');
});

it('registers the page_visits migration', function () {
    expect(Schema::hasTable('page_visits'))->toBeTrue();
});

it('registers the page_visit_daily_stats migration', function () {
    expect(Schema::hasTable('page_visit_daily_stats'))->toBeTrue();
});

it('registers the aggregate-and-prune command', function () {
    expect(Artisan::all())->toHaveKey('page-visits:aggregate-and-prune');
});
