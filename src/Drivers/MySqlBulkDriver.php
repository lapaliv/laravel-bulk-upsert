<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverDelete;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverInsertWithResult;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverQuietInsert;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverUpdate;
use Throwable;

/**
 * @internal
 */
class MySqlBulkDriver implements BulkDriver
{
    public function __construct(
        private MySqlDriverInsertWithResult $insertWithResult,
        private MySqlDriverQuietInsert $quietInsert,
        private MySqlDriverUpdate $update,
        private MySqlDriverDelete $delete,
    ) {
        //
    }

    /**
     * @throws Throwable
     */
    public function insertWithResult(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): BulkInsertResult {
        return $this->insertWithResult->handle($connection, $builder, $primaryKeyName);
    }

    /**
     * @throws Throwable
     */
    public function quietInsert(ConnectionInterface $connection, InsertBuilder $builder): void
    {
        $this->quietInsert->handle($connection, $builder);
    }

    /**
     * @throws Throwable
     */
    public function update(ConnectionInterface $connection, UpdateBulkBuilder $builder): int
    {
        return $this->update->handle($connection, $builder);
    }

    public function forceDelete(ConnectionInterface $connection, DeleteBulkBuilder $builder): int
    {
        return $this->delete->handle($connection, $builder);
    }
}
