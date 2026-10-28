<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Exceptions\BulkDriverIsNotSupported;

/**
 * The registry of the bulk drivers, keyed by the connection driver name.
 */
class DriverManager implements BulkDriverManager
{
    /**
     * The registered drivers keyed by their name.
     *
     * @var \Lapaliv\BulkUpsert\Contracts\BulkDriver[]
     */
    private array $drivers = [];

    /**
     * Get the driver which serves the connection of the given model.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     *
     * @return \Lapaliv\BulkUpsert\Contracts\BulkDriver
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkDriverIsNotSupported
     */
    public function getForModel(Model $eloquent): BulkDriver
    {
        $driverName = $eloquent->getConnection()->getDriverName();
        $driver = $this->get($driverName);

        if ($driver === null) {
            throw new BulkDriverIsNotSupported($driverName);
        }

        return $driver;
    }

    /**
     * Register the driver under the given name.
     *
     * @param string $name
     * @param \Lapaliv\BulkUpsert\Contracts\BulkDriver $driver
     *
     * @return void
     */
    public function registerDriver(string $name, BulkDriver $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    /**
     * Get the driver registered under the given name.
     *
     * @param string $name
     *
     * @return \Lapaliv\BulkUpsert\Contracts\BulkDriver|null
     */
    public function get(string $name): ?BulkDriver
    {
        return $this->drivers[$name] ?? null;
    }
}
