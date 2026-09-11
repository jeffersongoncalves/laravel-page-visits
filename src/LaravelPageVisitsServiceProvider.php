<?php

namespace JeffersonGoncalves\LaravelPageVisits;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPageVisitsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-page-visits')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations();
    }
}
