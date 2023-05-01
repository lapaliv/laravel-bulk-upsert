<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\Eloquent\Model;

interface BulkDriverManager
{
    /**
     * @param Model $eloquent
     *
     * @return BulkDriver
     */
    public function getForModel(Model $eloquent): BulkDriver;

    public function registerDriver(string $name, BulkDriver $driver): void;

    public function get(string $name): ?BulkDriver;

    /**
     * @return array<string, BulkDriver>
     */
    public function all(): array;
}
