<?php

use Illuminate\Support\Facades\Schema;

it('registers the config file', function () {
    expect(config('page-visits.table'))->toBe('page_visits');
});

it('registers the page_visits migration', function () {
    expect(Schema::hasTable('page_visits'))->toBeTrue();
});
