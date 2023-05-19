<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Drivers\PostgreSql\PostgreSqlDriverInsertWithResult;
use Lapaliv\BulkUpsert\Drivers\PostgreSql\PostgreSqlDriverQuietInsert;
use Lapaliv\BulkUpsert\Drivers\PostgreSql\PostgreSqlDriverUpdate;

class PostgreSqlBulkDriver implements BulkDriver
{
    public function __construct(
        private PostgreSqlDriverInsertWithResult $insertWithResult,
        private PostgreSqlDriverQuietInsert $quietInsert,
        private PostgreSqlDriverUpdate $update,
    ) {
        //
    }

    public function insertWithResult(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): BulkInsertResult {
        return $this->insertWithResult->handle($connection, $builder);
    }

    public function quietInsert(ConnectionInterface $connection, InsertBuilder $builder): void
    {
        $this->quietInsert->handle($connection, $builder);
    }

    public function update(ConnectionInterface $connection, UpdateBulkBuilder $builder): int
    {
        return $this->update->handle($connection, $builder);
    }
}
