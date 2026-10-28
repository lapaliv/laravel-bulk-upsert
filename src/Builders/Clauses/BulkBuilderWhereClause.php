<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses;

use Closure;

/**
 * A builder which can collect the conditions of a WHERE clause.
 *
 * @internal
 */
interface BulkBuilderWhereClause
{
    /**
     * Add a condition to the WHERE clause.
     *
     * When the field is a closure, the condition is a nested group which that
     * closure fills in later.
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
    ): static;

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
    public function orWhere(string|Closure $field, string $operator = '=', mixed $value = null): static;

    /**
     * Add an IN condition to the WHERE clause.
     *
     * @param string $field
     * @param array $values
     * @param string $boolean
     *
     * @return $this
     *
     * @psalm-api
     */
    public function whereIn(string $field, array $values, string $boolean = 'and'): static;
}
