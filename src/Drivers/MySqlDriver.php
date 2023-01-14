<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Contracts\Driver;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlDriverInsert;

class MySqlDriver implements Driver
{
    public function __construct(
        private MySqlDriverInsert $insertFeature
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

    public function update(
        ConnectionInterface $connection,
        UpdateBuilder $builder,
    )
    {

    }
}
