<?php

namespace JeffersonGoncalves\LaravelPageVisits\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JeffersonGoncalves\LaravelPageVisits\LaravelPageVisits
 */
class LaravelPageVisits extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-page-visits';
    }
}
