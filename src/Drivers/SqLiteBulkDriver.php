<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Drivers\SqLite\SqLiteDriverForceDeleteFeature;
use Lapaliv\BulkUpsert\Drivers\SqLite\SqLiteDriverInsertWithResultFeature;
use Lapaliv\BulkUpsert\Drivers\SqLite\SqLiteDriverQuietInsertFeature;
use Lapaliv\BulkUpsert\Drivers\SqLite\SqLiteDriverUpdateFeature;

class SqLiteBulkDriver implements BulkDriver
{
    public function __construct(
        private SqLiteDriverInsertWithResultFeature $insertWithResultFeature,
        private SqLiteDriverQuietInsertFeature $quietInsertFeature,
        private SqLiteDriverUpdateFeature $updateFeature,
        private SqLiteDriverForceDeleteFeature $forceDeleteFeature,
    ) {
        //
    }

    public function insertWithResult(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): BulkInsertResult {
        return $this->insertWithResultFeature->handle($connection, $builder, $primaryKeyName);
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
        return $this->forceDeleteFeature->handle($connection, $builder);
    }
}
