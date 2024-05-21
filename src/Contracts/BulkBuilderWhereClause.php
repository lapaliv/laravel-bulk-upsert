<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Closure;

/**
 * @internal
 */
interface BulkBuilderWhereClause
{
    public function where(
        string|Closure $field,
        string $operator = '=',
        mixed $value = null,
        string $boolean = 'and',
    ): static;

    /**
     * @param Closure|string $field
     * @param string $operator
     * @param mixed|null $value
     *
     * @return $this
     *
     * @psalm-api
     */
    public function orWhere(string|Closure $field, string $operator = '=', mixed $value = null): static;

    /**
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
