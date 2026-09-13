<?php

use Illuminate\Console\Scheduling\Schedule;

it('self-schedules aggregate-and-prune daily by default', function () {
    $events = app(Schedule::class)->events();

    $event = collect($events)->first(
        fn ($event) => str_contains($event->command ?? '', 'page-visits:aggregate-and-prune')
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 2 * * *');
});

it('does not schedule it when disabled via env', function () {
    putenv('PAGE_VISITS_SCHEDULE_AGGREGATE_AND_PRUNE=false');
    $_ENV['PAGE_VISITS_SCHEDULE_AGGREGATE_AND_PRUNE'] = 'false';

    $this->refreshApplication();

    $events = app(Schedule::class)->events();

    $event = collect($events)->first(
        fn ($event) => str_contains($event->command ?? '', 'page-visits:aggregate-and-prune')
    );

    expect($event)->toBeNull();

    putenv('PAGE_VISITS_SCHEDULE_AGGREGATE_AND_PRUNE');
    unset($_ENV['PAGE_VISITS_SCHEDULE_AGGREGATE_AND_PRUNE']);
});
