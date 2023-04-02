<?php

namespace Lapaliv\BulkUpsert;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\Driver;
use Lapaliv\BulkUpsert\Contracts\DriverManager;
use Lapaliv\BulkUpsert\Exceptions\BulkDriverIsNotSupported;

class BulkDriverManager implements DriverManager
{
    /**
     * @var Driver[]
     */
    private array $drivers = [];

    /**
     * @param BulkModel $eloquent
     *
     * @return Driver
     */
    public function getForModel(BulkModel $eloquent): Driver
    {
        $driverName = $eloquent->getConnection()->getDriverName();
        $driver = $this->get($driverName);

        if ($driver === null) {
            throw new BulkDriverIsNotSupported($driverName);
        }

        return $driver;
    }

    public function registerDriver(string $name, Driver $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    public function get(string $name): ?Driver
    {
        return $this->drivers[$name] ?? null;
    }

    /**
     * @return array<string, Driver>
     */
    public function all(): array
    {
        return $this->drivers;
    }
}
