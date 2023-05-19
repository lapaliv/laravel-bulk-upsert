<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;

interface BulkDriver
{
    public function insertWithResult(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): BulkInsertResult;

    public function quietInsert(ConnectionInterface $connection, InsertBuilder $builder): void;

    public function update(
        ConnectionInterface $connection,
        UpdateBulkBuilder $builder
    ): int;
}
