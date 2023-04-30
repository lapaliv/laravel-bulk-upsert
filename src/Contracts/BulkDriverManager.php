<?php

namespace Lapaliv\BulkUpsert\Contracts;

interface BulkDriverManager
{
    /**
     * @param BulkModel $eloquent
     *
     * @return BulkDriver
     */
    public function getForModel(BulkModel $eloquent): BulkDriver;

    public function registerDriver(string $name, BulkDriver $driver): void;

    public function get(string $name): ?BulkDriver;

    /**
     * @return array<string, BulkDriver>
     */
    public function all(): array;
}
