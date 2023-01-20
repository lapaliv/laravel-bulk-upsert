<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Illuminate\Contracts\Container\Container;
use Lapaliv\BulkUpsert\Contracts\DriverManager;
use Lapaliv\BulkUpsert\Tests\App\Drivers\NullDriver;
use Mockery;

class SwitchDriverToNullDriverFeature
{
    public function __construct(private Container $container)
    {
        //
    }

    public function handle(): void
    {
        $driverManagerMock = Mockery::mock(DriverManager::class);
        $driverManagerMock->expects('getForModel')
            ->andReturn(new NullDriver())
            ->atLeast()
            ->once();

        $this->container->singleton(DriverManager::class, fn () => $driverManagerMock);
    }
}
