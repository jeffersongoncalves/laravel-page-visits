<?php

use Illuminate\Support\Facades\DB;
use JeffersonGoncalves\LaravelPageVisits\Models\PageVisit;

it('folds yesterdays visits into a daily_stats row and prunes old visits', function () {
    config(['page-visits.retention_days' => 30]);

    PageVisit::query()->create([
        'visited_at' => now()->subDay()->setTime(10, 0),
        'path' => 'blog/hello-world',
        'method' => 'GET',
        'country_code' => 'BR',
        'is_bot' => false,
        'ip_hash' => 'hash-1',
        'created_at' => now()->subDay(),
    ]);
    PageVisit::query()->create([
        'visited_at' => now()->subDays(90),
        'path' => 'blog/old-post',
        'method' => 'GET',
        'is_bot' => false,
        'ip_hash' => 'hash-2',
        'created_at' => now()->subDays(90),
    ]);

    $this->artisan('page-visits:aggregate-and-prune')->assertExitCode(0);

    $row = DB::table('page_visit_daily_stats')
        ->where('date', now()->subDay()->toDateString())
        ->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->visits_count)->toBe(1)
        ->and(json_decode((string) $row->country_stats, true))->toBe(['BR' => 1])
        ->and(PageVisit::query()->count())->toBe(1);
});

it('accepts a --date option to backfill a specific day', function () {
    $date = now()->subDays(5);

    PageVisit::query()->create([
        'visited_at' => $date->copy()->setTime(14, 0),
        'path' => 'blog/hello-world',
        'method' => 'GET',
        'is_bot' => true,
        'created_at' => $date,
    ]);

    $this->artisan('page-visits:aggregate-and-prune', ['--date' => $date->toDateString()])
        ->assertExitCode(0);

    $row = DB::table('page_visit_daily_stats')->where('date', $date->toDateString())->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->bot_visits_count)->toBe(1)
        ->and((int) $row->visits_count)->toBe(0);
});
