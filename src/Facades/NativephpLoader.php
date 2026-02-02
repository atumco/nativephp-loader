<?php

namespace Atum\NativephpLoader\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed execute(array $options = [])
 * @method static object|null getStatus()
 *
 * @see \Atum\NativephpLoader\NativephpLoader
 */
class NativephpLoader extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Atum\NativephpLoader\NativephpLoader::class;
    }
}