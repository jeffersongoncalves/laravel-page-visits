<?php

namespace JeffersonGoncalves\LaravelPageVisits\Tests;

use JeffersonGoncalves\LaravelPageVisits\LaravelPageVisitsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelPageVisitsServiceProvider::class,
        ];
    }
}
