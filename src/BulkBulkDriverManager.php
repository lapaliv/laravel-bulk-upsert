<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Exceptions\BulkDriverIsNotSupported;

final class BulkBulkDriverManager implements BulkDriverManager
{
    /**
     * @var BulkDriver[]
     */
    private array $drivers = [];

    /**
     * @param Model $eloquent
     *
     * @return BulkDriver
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

    public function registerDriver(string $name, BulkDriver $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    public function get(string $name): ?BulkDriver
    {
        return $this->drivers[$name] ?? null;
    }
}
