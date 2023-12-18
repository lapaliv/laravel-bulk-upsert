<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverDeleteFeature;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverInsertWithResultFeature;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverQuietInsertFeature;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverUpdateFeature;
use Throwable;

/**
 * @internal
 */
class MySqlBulkDriver implements BulkDriver
{
    public function __construct(
        private MySqlDriverInsertWithResultFeature $insertWithResultFeature,
        private MySqlDriverQuietInsertFeature $quietInsertFeature,
        private MySqlDriverUpdateFeature $updateFeature,
        private MySqlDriverDeleteFeature $deleteFeature,
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
        return $this->insertWithResultFeature->handle($connection, $builder, $primaryKeyName);
    }

    /**
     * @throws Throwable
     */
    public function quietInsert(ConnectionInterface $connection, InsertBuilder $builder): void
    {
        $this->quietInsertFeature->handle($connection, $builder);
    }

    /**
     * @throws Throwable
     */
    public function update(ConnectionInterface $connection, UpdateBulkBuilder $builder): int
    {
        return $this->updateFeature->handle($connection, $builder);
    }

    public function forceDelete(ConnectionInterface $connection, DeleteBulkBuilder $builder): int
    {
        return $this->deleteFeature->handle($connection, $builder);
    }
}
