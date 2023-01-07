<?php

namespace Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses;

use Closure;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\WhereClause\BulkSqlBuilderWhereClauseCallback;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\WhereClause\BulkSqlBuilderWhereClauseCondition;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\WhereClause\BulkSqlBuilderWhereClauseIn;

class BulkSqlBuilderWhereClause
{
    /** @var BulkSqlBuilderWhereClauseCallback[]|BulkSqlBuilderWhereClauseCondition[]|BulkSqlBuilderWhereClauseIn[] */
    private array $wheres = [];

    public function getWheres(): array
    {
        return $this->wheres;
    }

    public function orWhere(
        string|Closure $field,
        string $operator = '=',
        mixed $value = null,
    ): static
    {
        $this->where($field, $operator, $value, 'or');

        return $this;
    }

    public function where(
        string|Closure $field,
        string $operator = '=',
        mixed $value = null,
        string $boolean = 'and'
    ): static
    {
        if ($field instanceof Closure) {
            $this->wheres[] = new BulkSqlBuilderWhereClauseCallback(
                $field,
                $boolean
            );
        } else {
            $this->wheres[] = new BulkSqlBuilderWhereClauseCondition(
                $field,
                $operator,
                $value,
                $boolean,
            );
        }

        return $this;
    }

    public function whereIn(
        string $field,
        array $values,
        string $boolean = 'and'
    ): static
    {
        $this->wheres[] = new BulkSqlBuilderWhereClauseIn(
            $field,
            $values,
            $boolean,
        );

        return $this;
    }
}
