<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;

interface BulkDriver
{
    public function insert(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
        array $selectColumns,
    ): BulkInsertResult;

    public function update(
        ConnectionInterface $connection,
        UpdateBulkBuilder $builder
    ): int;
}
