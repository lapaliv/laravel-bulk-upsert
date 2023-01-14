<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;

interface Driver
{
    public function insert(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): int|string|null;
}
