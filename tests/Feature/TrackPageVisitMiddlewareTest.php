<?php

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\LaravelPageVisits\Jobs\TrackPageVisitJob;

beforeEach(function () {
    Route::get('/hello', fn () => 'hi')->middleware('web');
    Route::get('/admin', fn () => 'admin-root')->middleware('web');
    Route::get('/admin/dashboard', fn () => 'admin')->middleware('web');
    Route::get('/{key}', fn () => 'redirect')->middleware('web')->name('short-url.redirect');
});

it('dispatches the tracking job for an included path', function () {
    Queue::fake();

    $this->get('/hello')->assertOk();

    Queue::assertPushed(TrackPageVisitJob::class, fn (TrackPageVisitJob $job) => $job->payload['path'] === 'hello');
});

it('skips excluded paths defined via glob patterns in config', function () {
    Queue::fake();

    $this->get('/admin/dashboard')->assertOk();

    Queue::assertNotPushed(TrackPageVisitJob::class);
});

it('skips the bare root of an excluded prefix, not just its sub-paths', function () {
    Queue::fake();

    $this->get('/admin')->assertOk();

    Queue::assertNotPushed(TrackPageVisitJob::class);
});

it('skips non-GET requests', function () {
    Route::post('/hello', fn () => 'hi')->middleware('web');
    Queue::fake();

    $this->post('/hello')->assertOk();

    Queue::assertNotPushed(TrackPageVisitJob::class);
});

it('skips routes excluded by name, even when the path is unpredictable', function () {
    config(['page-visits.exclude_route_names' => ['short-url.redirect']]);
    Queue::fake();

    $this->get('/2kLwqwP')->assertOk();

    Queue::assertNotPushed(TrackPageVisitJob::class);
});

it('skips bot requests when store_bots is off but still tracks humans', function () {
    config(['page-visits.store_bots' => false]);
    Queue::fake();

    $this->withHeader('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)')->get('/hello')->assertOk();
    Queue::assertNotPushed(TrackPageVisitJob::class);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36')->get('/hello')->assertOk();
    Queue::assertPushed(TrackPageVisitJob::class, fn (TrackPageVisitJob $job) => $job->payload['is_bot'] === false);
});

it('tracks bot requests by default', function () {
    Queue::fake();

    $this->withHeader('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)')->get('/hello')->assertOk();

    Queue::assertPushed(TrackPageVisitJob::class, fn (TrackPageVisitJob $job) => $job->payload['is_bot'] === true);
});

it('snapshots app()->getLocale() at request time, not inside the queued job', function () {
    Queue::fake();
    app()->setLocale('pt_BR');

    $this->get('/hello')->assertOk();

    Queue::assertPushed(TrackPageVisitJob::class, fn (TrackPageVisitJob $job) => $job->payload['locale'] === 'pt_BR');
});
