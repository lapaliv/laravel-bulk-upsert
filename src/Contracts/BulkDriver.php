<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;

/**
 * Runs the bulk queries on the connection of one database dialect.
 */
interface BulkDriver
{
    /**
     * Insert the rows and return the result which helps to find them again.
     *
     * The primary key name is `null` when the model does not have a single
     * incrementing key.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     * @param string|null $primaryKeyName
     *
     * @return \Lapaliv\BulkUpsert\Contracts\BulkInsertResult
     *
     * @throws \Throwable
     */
    public function insertWithResult(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): BulkInsertResult;

    /**
     * Insert the rows without collecting any result.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return void
     *
     * @throws \Throwable
     *
     * @psalm-api
     */
    public function quietInsert(ConnectionInterface $connection, InsertBuilder $builder): void;

    /**
     * Run the update query and return the number of the affected rows.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder $builder
     *
     * @return int
     *
     * @throws \Throwable
     *
     * @psalm-api
     */
    public function update(ConnectionInterface $connection, UpdateBulkBuilder $builder): int;

    /**
     * Run the delete query and return the number of the deleted rows.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder $builder
     *
     * @return int
     *
     * @throws \Throwable
     *
     * @psalm-api
     */
    public function forceDelete(ConnectionInterface $connection, DeleteBulkBuilder $builder): int;
}
