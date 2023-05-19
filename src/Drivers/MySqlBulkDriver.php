<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverInsert;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverUpdate;
use Throwable;

/**
 * @internal
 */
class MySqlBulkDriver implements BulkDriver
{
    public function __construct(
        private MySqlDriverInsert $insert,
        private MySqlDriverUpdate $update
    ) {
        //
    }

    /**
     * @throws Throwable
     */
    public function insert(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
        array $selectColumns,
    ): BulkInsertResult {
        return $this->insert->handle($connection, $builder, $primaryKeyName);
    }

    /**
     * @throws Throwable
     */
    public function update(ConnectionInterface $connection, UpdateBulkBuilder $builder): int
    {
        return $this->update->handle($connection, $builder);
    }
}
