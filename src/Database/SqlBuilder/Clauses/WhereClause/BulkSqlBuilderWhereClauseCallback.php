<?php

namespace Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\WhereClause;

use Closure;

class BulkSqlBuilderWhereClauseCallback
{
    public function __construct(
        public Closure $callback,
        public string $boolean
    )
    {
        //
    }
}
