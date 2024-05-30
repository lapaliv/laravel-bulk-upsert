<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;

interface BulkDriver
{
    public function insertWithResult(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): BulkInsertResult;

    /**
     * @param ConnectionInterface $connection
     * @param InsertBuilder $builder
     *
     * @return void
     *
     * @psalm-api
     */
    public function quietInsert(ConnectionInterface $connection, InsertBuilder $builder): void;

    /**
     * @param ConnectionInterface $connection
     * @param UpdateBulkBuilder $builder
     *
     * @return int
     *
     * @psalm-api
     */
    public function update(ConnectionInterface $connection, UpdateBulkBuilder $builder): int;

    /**
     * @param ConnectionInterface $connection
     * @param DeleteBulkBuilder $builder
     *
     * @return int
     *
     * @psalm-api
     */
    public function forceDelete(ConnectionInterface $connection, DeleteBulkBuilder $builder): int;
}
