<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Contracts\Driver;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverInsert;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverUpdate;
use Throwable;

/**
 * @internal
 */
class MySqlDriver implements Driver
{
    public function __construct(
        private MySqlDriverInsert $insertFeature,
        private MySqlDriverUpdate $updateFeature
    ) {
        //
    }

    /**
     * @throws Throwable
     */
    public function insert(ConnectionInterface $connection, InsertBuilder $builder, ?string $primaryKeyName): ?int
    {
        return $this->insertFeature->handle($connection, $builder, $primaryKeyName);
    }

    public function simpleInsert(Builder $builder, array $values): void
    {
        $builder->insert($values);
    }

    /**
     * @throws Throwable
     */
    public function update(ConnectionInterface $connection, UpdateBuilder $builder): int
    {
        return $this->updateFeature->handle($connection, $builder);
    }
}
