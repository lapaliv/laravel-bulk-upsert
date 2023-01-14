<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderInsert;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderUpdate;

interface BulkDatabaseProcessor
{
    public function insert(BulkSqlBuilderInsert $builder): array;

    public function update(BulkSqlBuilderUpdate $builder): array;
}
