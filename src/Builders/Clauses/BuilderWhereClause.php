<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses;

use Closure;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCallback;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCondition;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereIn;

/**
 * Gives a builder the ability to collect the conditions of a WHERE clause.
 *
 * @internal
 *
 * @psalm-api
 */
trait BuilderWhereClause
{
    /**
     * The collected conditions of the WHERE clause.
     *
     * @var \Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCallback[]|\Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCondition[]|\Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereIn[]
     */
    private array $wheres = [];

    /**
     * The names of the columns which are used in the conditions.
     *
     * @var array<string, string>
     */
    private array $fields = [];

    /**
     * Add a condition to the WHERE clause.
     *
     * When the field is a closure, the condition is stored as a nested group
     * which is filled in by that closure later.
     *
     * @param \Closure|string $field
     * @param string $operator
     * @param mixed $value
     * @param string $boolean
     *
     * @return $this
     */
    public function where(
        string|Closure $field,
        string $operator = '=',
        mixed $value = null,
        string $boolean = 'and',
    ): static
    {
        if ($field instanceof Closure) {
            $this->wheres[] = new BuilderWhereCallback($field, $boolean);
        } else {
            $this->fields[$field] = $field;
            $this->wheres[] = new BuilderWhereCondition($field, $operator, $value, $boolean);
        }

        return $this;
    }

    /**
     * Add a condition to the WHERE clause joined by the OR keyword.
     *
     * @param \Closure|string $field
     * @param string $operator
     * @param mixed|null $value
     *
     * @return $this
     *
     * @psalm-api
     */
    public function orWhere(string|Closure $field, string $operator = '=', mixed $value = null): static
    {
        return $this->where($field, $operator, $value, 'or');
    }

    /**
     * Add an IN condition to the WHERE clause.
     *
     * @param string $field
     * @param scalar[] $values
     * @param string $boolean
     *
     * @return $this
     *
     * @psalm-suppress MoreSpecificImplementedParamType Lapaliv\BulkUpsert\Builders\Clauses\BulkBuilderWhereClause::whereIn() (outside this file's edit scope) declares an untyped `array $values`; every implementation and caller of `whereIn()` only ever passes scalar values (they end up in Where\BuilderWhereIn, whose constructor requires array<scalar|null>), so narrowing here is intentional and safe.
     */
    public function whereIn(string $field, array $values, string $boolean = 'and'): static
    {
        $this->fields[$field] = $field;
        $this->wheres[] = new BuilderWhereIn($field, $values, $boolean);

        return $this;
    }

    /**
     * Get all the collected conditions of the WHERE clause.
     *
     * @return \Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCallback[]|\Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCondition[]|\Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereIn[]
     */
    public function getWheres(): array
    {
        return $this->wheres;
    }
}
