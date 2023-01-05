<?php

namespace Lapaliv\BulkUpsert;

use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;

class BulkUpsert
{
    private static array $databaseDrivers = [];

    public static function registerDatabaseDriver(string $name, BulkDatabaseDriver $driver): void
    {
        self::$databaseDrivers[$name] = $driver;
    }

    public static function getDatabaseDriver(string $name): ?BulkDatabaseDriver
    {
        return self::$databaseDrivers[$name] ?? null;
    }
}
