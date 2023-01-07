<?php

namespace Lapaliv\BulkUpsert\Database;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\BulkSqlBuilderWhereClause;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderUpdateOperation;

class BulkSqlBuilder
{
    public function update(): BulkSqlBuilderUpdateOperation
    {
        return new BulkSqlBuilderUpdateOperation(
            new BulkSqlBuilderWhereClause(),
        );
    }
}
