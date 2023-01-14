<?php

namespace Lapaliv\BulkUpsert\Drivers\MySql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;

class MySqlDriverUpdate
{
    public function __construct()
    {

    }

    public function handle(
        ConnectionInterface $connection,
        UpdateBuilder $builder,
    ): void
    {

    }

    private function generateSql(UpdateBuilder $builder): array
    {

    }
}
