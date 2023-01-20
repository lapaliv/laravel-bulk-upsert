<?php

namespace Lapaliv\BulkUpsert\Tests\App\Drivers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Contracts\Driver;

class NullDriver implements Driver
{
    public function insert(ConnectionInterface $connection, InsertBuilder $builder, ?string $primaryKeyName): int|string|null
    {
        return null;
    }

    public function update(ConnectionInterface $connection, UpdateBuilder $builder): int
    {
        return 0;
    }

    public function simpleInsert(Builder $builder, array $values): void
    {
        return;
    }
}
