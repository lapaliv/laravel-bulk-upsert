<?php

namespace Lapaliv\BulkUpsert\Database;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\BulkSqlBuilderWhereClause;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderInsert;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderUpdate;

class BulkSqlBuilder
{
    public function insert(): BulkSqlBuilderInsert
    {
        return new BulkSqlBuilderInsert();
    }

    public function update(): BulkSqlBuilderUpdate
    {
        return new BulkSqlBuilderUpdate(
            new BulkSqlBuilderWhereClause(),
        );
    }
}
