<?php

namespace JeffersonGoncalves\LaravelPageVisits;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as FoundationKernel;
use JeffersonGoncalves\LaravelPageVisits\Http\Middleware\TrackPageVisit;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPageVisitsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-page-visits')
            ->hasConfigFile('page-visits')
            ->hasMigration('create_page_visits_table');
    }

    public function packageBooted(): void
    {
        if (! config('page-visits.auto_register_middleware', true)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        // appendMiddlewareToGroup() (not Router::pushMiddlewareToGroup())
        // because resolving the Kernel contract later (on the first request)
        // re-syncs its own $middlewareGroups onto the router, silently
        // wiping out anything pushed onto the router directly before the
        // Kernel was constructed. Every real app kernel extends the
        // Foundation base that defines this method — the instanceof guard
        // just keeps this a no-op against an exotic non-standard binding.
        if ($kernel instanceof FoundationKernel) {
            $kernel->appendMiddlewareToGroup('web', TrackPageVisit::class);
        }
    }
}
