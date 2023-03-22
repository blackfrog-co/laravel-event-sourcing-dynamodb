<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \BlackFrog\LaravelEventSourcingDynamodb\LaravelEventSourcingDynamodb
 */
class LaravelEventSourcingDynamodb extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \BlackFrog\LaravelEventSourcingDynamodb\LaravelEventSourcingDynamodb::class;
    }
}
