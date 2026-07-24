<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * The registry of the bulk drivers, keyed by the name of the connection driver.
 */
interface BulkDriverManager
{
    /**
     * Get the driver which serves the connection of the given model.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     *
     * @return \Lapaliv\BulkUpsert\Contracts\BulkDriver
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkDriverIsNotSupported
     */
    public function getForModel(Model $eloquent): BulkDriver;

    /**
     * Register the driver under the given name.
     *
     * @param string $name
     * @param \Lapaliv\BulkUpsert\Contracts\BulkDriver $driver
     *
     * @return void
     */
    public function registerDriver(string $name, BulkDriver $driver): void;

    /**
     * Get the driver which is registered under the given name.
     *
     * @param string $name
     *
     * @return \Lapaliv\BulkUpsert\Contracts\BulkDriver|null
     */
    public function get(string $name): ?BulkDriver;
}
