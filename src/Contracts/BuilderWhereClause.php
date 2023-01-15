<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Closure;

interface BuilderWhereClause
{
    public function where(
        string|Closure $field,
        string $operator = '=',
        mixed $value = null,
        string $boolean = 'and',
    ): static;

    public function orWhere(string|Closure $field, string $operator = '=', mixed $value = null): static;

    public function whereIn(string $field, array $values, string $boolean = 'and'): static;
}
