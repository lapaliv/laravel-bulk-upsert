<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;

interface Driver
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

    public function simpleInsert(Builder $builder, array $values): void;
}
