<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Drivers\PostgreSql\PostgreSqlDriverDeleteFeature;
use Lapaliv\BulkUpsert\Drivers\PostgreSql\PostgreSqlDriverInsertWithResultFeature;
use Lapaliv\BulkUpsert\Drivers\PostgreSql\PostgreSqlDriverQuietInsertFeature;
use Lapaliv\BulkUpsert\Drivers\PostgreSql\PostgreSqlDriverUpdateFeature;

class PostgreSqlBulkDriver implements BulkDriver
{
    public function __construct(
        private PostgreSqlDriverInsertWithResultFeature $insertWithResultFeature,
        private PostgreSqlDriverQuietInsertFeature $quietInsertFeature,
        private PostgreSqlDriverUpdateFeature $updateFeature,
        private PostgreSqlDriverDeleteFeature $deleteFeature,
    ) {
        //
    }

    public function insertWithResult(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): BulkInsertResult {
        return $this->insertWithResultFeature->handle($connection, $builder);
    }

    public function quietInsert(ConnectionInterface $connection, InsertBuilder $builder): void
    {
        $this->quietInsertFeature->handle($connection, $builder);
    }

    public function update(ConnectionInterface $connection, UpdateBulkBuilder $builder): int
    {
        return $this->updateFeature->handle($connection, $builder);
    }

    public function forceDelete(ConnectionInterface $connection, DeleteBulkBuilder $builder): int
    {
        return $this->deleteFeature->handle($connection, $builder);
    }
}
