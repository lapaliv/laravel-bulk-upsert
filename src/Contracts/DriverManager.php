<?php

namespace Lapaliv\BulkUpsert\Contracts;

interface DriverManager
{
    /**
     * @param BulkModel $eloquent
     * @return Driver
     */
    public function getForModel(BulkModel $eloquent): Driver;

    public function registerDriver(string $name, Driver $driver): void;

    public function get(string $name): ?Driver;

    /**
     * @return array<string, Driver>
     */
    public function all(): array;
}
