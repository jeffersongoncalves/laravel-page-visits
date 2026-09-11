<?php

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\LaravelPageVisits\Jobs\TrackPageVisitJob;

beforeEach(function () {
    Route::get('/hello', fn () => 'hi')->middleware('web');
    Route::get('/admin/dashboard', fn () => 'admin')->middleware('web');
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

it('skips non-GET requests', function () {
    Route::post('/hello', fn () => 'hi')->middleware('web');
    Queue::fake();

    $this->post('/hello')->assertOk();

    Queue::assertNotPushed(TrackPageVisitJob::class);
});

it('snapshots app()->getLocale() at request time, not inside the queued job', function () {
    Queue::fake();
    app()->setLocale('pt_BR');

    $this->get('/hello')->assertOk();

    Queue::assertPushed(TrackPageVisitJob::class, fn (TrackPageVisitJob $job) => $job->payload['locale'] === 'pt_BR');
});
