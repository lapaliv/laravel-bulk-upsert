<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;

interface BulkDriver
{
    public function insert(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): ?int;

    public function update(
        ConnectionInterface $connection,
        UpdateBuilder $builder
    ): int;
}
