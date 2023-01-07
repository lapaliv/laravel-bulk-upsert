<?php

namespace Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\WhereClause;

class BulkSqlBuilderWhereClauseIn
{
    public function __construct(
        public string $field,
        public array $values,
        public string $boolean
    )
    {
        //
    }
}
