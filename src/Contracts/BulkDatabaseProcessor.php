<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderUpdateOperation;

interface BulkDatabaseProcessor
{
//    public function insert(BulkSqlBuilder $sqlBuilder): array;

    public function update(BulkSqlBuilderUpdateOperation $builder): array;
}
