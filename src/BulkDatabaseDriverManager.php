<?php

namespace Lapaliv\BulkUpsert;

use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;

class BulkDatabaseDriverManager
{
    private static array $drivers = [];

    public static function registerDriver(string $name, BulkDatabaseDriver $driver): void
    {
        self::$drivers[$name] = $driver;
    }

    public static function get(string $name): ?BulkDatabaseDriver
    {
        return self::$drivers[$name] ?? null;
    }
}
