<?php

namespace Eventrel\Client\Facades;

use Illuminate\Support\Facades\Facade;

class Eventrel extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'eventrel';
    }
}
