<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses;

use Closure;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCallback;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCondition;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereIn;

trait BuilderWhere
{
    private array $wheres = [];
    private array $fields = [];

    public function where(string|Closure $field, string $operator, mixed $value, string $boolean = 'and'): static
    {
        $this->fields[$field] = $field;
        $this->wheres[] = $field instanceof Closure
            ? new BuilderWhereCallback($field, $boolean)
            : new BuilderWhereCondition($field, $operator, $value, $boolean);

        return $this;
    }

    public function orWhere(string|Closure $field, string $operator, mixed $value): static
    {
        return $this->where($field, $operator, $value, 'or');
    }

    public function whereIn(string $field, array $values, string $boolean = 'and'): static
    {
        $this->fields[$field] = $field;
        $this->wheres[] = new BuilderWhereIn($field, $values, $boolean);

        return $this;
    }

    public function getWheres(): array
    {
        return $this->wheres;
    }

    public function getFields(): array
    {
        return array_values($this->fields);
    }
}
