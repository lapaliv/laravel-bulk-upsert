<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Drivers\PostgreSql\PostgreSqlDriverInsert;
use Lapaliv\BulkUpsert\Drivers\PostgreSql\PostgreSqlDriverUpdate;

class PostgreSqlBulkDriver implements BulkDriver
{
    public function __construct(
        private PostgreSqlDriverInsert $insert,
        private PostgreSqlDriverUpdate $update,
    ) {
        //
    }

    public function insert(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
        array $selectColumns,
    ): BulkInsertResult {
        return $this->insert->handle($connection, $builder, $selectColumns);
    }

    public function update(ConnectionInterface $connection, UpdateBulkBuilder $builder): int
    {
        return $this->update->handle($connection, $builder);
    }
}
