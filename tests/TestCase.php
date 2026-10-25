<?php

namespace JeffersonGoncalves\LaravelPageVisits\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JeffersonGoncalves\LaravelPageVisits\LaravelPageVisitsServiceProvider;
use JeffersonGoncalves\PestPluginDatabaseMigrations\LoadsPackageMigrations;
use JeffersonGoncalves\VisitorFingerprint\VisitorFingerprintServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use LoadsPackageMigrations;
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
        $app['config']->set('database.connections.testing', $this->testing_connection());
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadPackageMigrations(LaravelPageVisitsServiceProvider::class);
    }

    /**
     * The original in-memory SQLite connection by default; CI (tests.yml) sets
     * PAGE_VISITS_TEST_DB_* to run the same suite on MySQL and PostgreSQL. Not DB_CONNECTION:
     * Testbench pins it to "testing", which would always win over a driver read from it.
     *
     * @return array<string, mixed>
     */
    protected function testing_connection(): array
    {
        $driver = env('PAGE_VISITS_TEST_DB_DRIVER', 'sqlite');

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ];
        }

        return [
            'driver' => $driver,
            'host' => env('PAGE_VISITS_TEST_DB_HOST', '127.0.0.1'),
            'port' => env('PAGE_VISITS_TEST_DB_PORT'),
            'database' => env('PAGE_VISITS_TEST_DB_DATABASE', 'testing'),
            'username' => env('PAGE_VISITS_TEST_DB_USERNAME', 'root'),
            'password' => env('PAGE_VISITS_TEST_DB_PASSWORD', ''),
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
        ];
    }
}
