<?php

namespace JeffersonGoncalves\LaravelPageVisits\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JeffersonGoncalves\LaravelPageVisits\LaravelPageVisitsServiceProvider;
use JeffersonGoncalves\VisitorFingerprint\VisitorFingerprintServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            VisitorFingerprintServiceProvider::class,
            LaravelPageVisitsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $stub = __DIR__.'/../database/migrations/create_page_visits_table.php.stub';
        $tempPath = sys_get_temp_dir().'/laravel-page-visits-migrations';

        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        copy($stub, $tempPath.'/0001_01_01_000000_create_page_visits_table.php');

        $this->loadMigrationsFrom($tempPath);
    }
}
