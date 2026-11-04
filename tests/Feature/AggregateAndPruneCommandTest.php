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

it('counts datacenter visits apart from human visits', function () {
    foreach ([[false, false, 'hash-1'], [false, true, 'hash-2'], [false, true, 'hash-3'], [true, true, 'hash-4']] as [$isBot, $isDatacenter, $ipHash]) {
        PageVisit::query()->create([
            'visited_at' => now()->subDay()->setTime(10, 0),
            'path' => 'blog/hello-world',
            'method' => 'GET',
            'is_bot' => $isBot,
            'is_datacenter' => $isDatacenter,
            'ip_hash' => $ipHash,
            'created_at' => now()->subDay(),
        ]);
    }

    $this->artisan('page-visits:aggregate-and-prune')->assertExitCode(0);

    $row = DB::table('page_visit_daily_stats')->where('date', now()->subDay()->toDateString())->first();

    expect((int) $row->visits_count)->toBe(1)
        ->and((int) $row->unique_visits_count)->toBe(1)
        ->and((int) $row->datacenter_visits_count)->toBe(2)
        ->and((int) $row->bot_visits_count)->toBe(1);
});

it('moves user agents seen from more than farm_ua_ip_threshold IPs into bot visits', function () {
    config(['page-visits.farm_ua_ip_threshold' => 2]);

    $visit = fn (string $ua, string $ip) => PageVisit::query()->create([
        'visited_at' => now()->subDay()->setTime(10, 0),
        'path' => 'blog/hello-world',
        'method' => 'GET',
        'is_bot' => false,
        'ip_hash' => $ip,
        'user_agent_hash' => $ua,
        'created_at' => now()->subDay(),
    ]);

    foreach (['ip-1', 'ip-2', 'ip-3'] as $ip) {
        $visit('farm-ua', $ip);
    }
    $visit('real-ua', 'ip-4');
    $visit('real-ua', 'ip-4');
    $visit('real-ua', 'ip-5');

    $this->artisan('page-visits:aggregate-and-prune')->assertExitCode(0);

    $row = DB::table('page_visit_daily_stats')->where('date', now()->subDay()->toDateString())->first();

    expect((int) $row->visits_count)->toBe(3)
        ->and((int) $row->unique_visits_count)->toBe(2)
        ->and((int) $row->bot_visits_count)->toBe(3)
        // Raw rows keep their capture-time flag, so turning the threshold off restores them.
        ->and(PageVisit::query()->where('is_bot', true)->count())->toBe(0);

    config(['page-visits.farm_ua_ip_threshold' => null]);
    $this->artisan('page-visits:aggregate-and-prune')->assertExitCode(0);

    expect((int) DB::table('page_visit_daily_stats')->where('date', now()->subDay()->toDateString())->value('visits_count'))->toBe(6);
});

it('exposes the farm lookup and an excludingFarms scope on the model', function () {
    $from = now()->subDay()->startOfDay();
    $to = $from->copy()->addDay();

    $visit = fn (?string $ua, string $ip, $at = null) => PageVisit::query()->create([
        'visited_at' => $at ?? $from->copy()->setTime(10, 0),
        'path' => 'blog/hello-world',
        'method' => 'GET',
        'is_bot' => false,
        'ip_hash' => $ip,
        'user_agent_hash' => $ua,
        'created_at' => now(),
    ]);

    foreach (['ip-1', 'ip-2', 'ip-3'] as $ip) {
        $visit('farm-ua', $ip);
    }
    $visit('real-ua', 'ip-4');
    $visit(null, 'ip-5');
    // Outside [$from, $to): must not push other-ua over the threshold.
    $visit('other-ua', 'ip-6');
    $visit('other-ua', 'ip-7', $to);
    $visit('other-ua', 'ip-8', $to->copy()->addHour());

    expect(PageVisit::farmUserAgentHashes($from, $to)->all())->toBe([]);

    config(['page-visits.farm_ua_ip_threshold' => 2]);

    expect(PageVisit::farmUserAgentHashes($from, $to)->all())->toBe(['farm-ua'])
        ->and(PageVisit::query()->where('visited_at', '<', $to)->excludingFarms($from, $to)->count())->toBe(3);

    config(['page-visits.farm_ua_ip_threshold' => '0']);

    expect(PageVisit::query()->excludingFarms($from, $to)->count())->toBe(8);
});

it('includes visits exactly at $from and excludes visits before it, rounding fractional bounds up', function () {
    config(['page-visits.farm_ua_ip_threshold' => 2]);

    $from = now()->subDay()->startOfDay();
    $to = $from->copy()->addDay();

    $visit = fn (string $ua, string $ip, $at) => PageVisit::query()->create([
        'visited_at' => $at,
        'path' => 'blog/hello-world',
        'method' => 'GET',
        'is_bot' => false,
        'ip_hash' => $ip,
        'user_agent_hash' => $ua,
        'created_at' => now(),
    ]);

    foreach (['ip-1', 'ip-2', 'ip-3'] as $ip) {
        $visit('before-ua', $ip, $from->copy()->subSecond());
        $visit('edge-ua', $ip, $from->copy());
    }

    expect(PageVisit::farmUserAgentHashes($from, $to)->all())->toBe(['edge-ua'])
        // A bound half a second before $from rounds up to $from, not down to before-ua's second.
        ->and(PageVisit::farmUserAgentHashes($from->copy()->subMicroseconds(500000), $to)->all())->toBe(['edge-ua']);
});

it('aggregates response time metrics', function () {
    foreach ([...range(10, 100, 10), null] as $ms) {
        PageVisit::query()->create([
            'visited_at' => now()->subDay()->setTime(10, 0),
            'path' => 'blog/hello-world',
            'method' => 'GET',
            'is_bot' => false,
            'response_time_ms' => $ms,
            'created_at' => now()->subDay(),
        ]);
    }

    $this->artisan('page-visits:aggregate-and-prune')->assertExitCode(0);

    $row = DB::table('page_visit_daily_stats')->where('date', now()->subDay()->toDateString())->first();

    // ksort: MySQL's JSON column reorders keys.
    $stats = json_decode((string) $row->response_time_stats, true);
    ksort($stats);

    expect($stats)->toBe([
        'avg' => 55,
        'count' => 10,
        'max' => 100,
        'min' => 10,
        'p50' => 50,
        'p95' => 100,
        'p99' => 100,
    ]);
});

it('stores null response time metrics when no visit has a timing', function () {
    PageVisit::query()->create([
        'visited_at' => now()->subDay()->setTime(10, 0),
        'path' => 'blog/hello-world',
        'method' => 'GET',
        'is_bot' => false,
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('page-visits:aggregate-and-prune')->assertExitCode(0);

    expect(DB::table('page_visit_daily_stats')->value('response_time_stats'))->toBeNull();
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
