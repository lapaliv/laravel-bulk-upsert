<?php

namespace Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\WhereClause;

class BulkSqlBuilderWhereClauseCondition
{
    public function __construct(
        public string $field,
        public string $operator,
        public mixed $value,
        public string $boolean
    )
    {
        //
    }
}
