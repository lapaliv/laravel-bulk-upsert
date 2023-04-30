<?php

namespace Lapaliv\BulkUpsert;

use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Exceptions\BulkDriverIsNotSupported;

final class BulkBulkDriverManager implements BulkDriverManager
{
    /**
     * @var BulkDriver[]
     */
    private array $drivers = [];

    /**
     * @param BulkModel $eloquent
     *
     * @return BulkDriver
     */
    public function getForModel(BulkModel $eloquent): BulkDriver
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

    /**
     * @return array<string, BulkDriver>
     */
    public function all(): array
    {
        return $this->drivers;
    }
}
