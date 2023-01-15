<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Contracts\Driver;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverInsert;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverUpdate;

class MySqlDriver implements Driver
{
    public function __construct(
        private MySqlDriverInsert $insertFeature,
        private MySqlDriverUpdate $updateFeature
    )
    {
        //
    }

    public function insert(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): int|string|null
    {
        return $this->insertFeature->handle($connection, $builder, $primaryKeyName);
    }

    public function simpleInsert(Builder $builder, array $values): void
    {
        $builder->insert($values);
    }

    public function update(ConnectionInterface $connection, UpdateBuilder $builder): int
    {
        return $this->updateFeature->handle($connection, $builder);
    }
}
