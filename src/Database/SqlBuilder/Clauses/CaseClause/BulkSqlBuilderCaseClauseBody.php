<?php

namespace Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\CaseClause;

class BulkSqlBuilderCaseClauseBody
{
    private array $wheres = [];
    private mixed $then;
    private array $fields = [];

    public function getWheres(): array
    {
        return $this->wheres;
    }

    public function where(string $field, string $operator = '=', mixed $value = null): static
    {
        $this->fields[$field] = $field;
        $this->wheres[] = compact('field', 'operator', 'value');

        return $this;
    }

    public function getThen(): mixed
    {
        return $this->then;
    }

    public function then(mixed $value): static
    {
        $this->then = $value;

        return $this;
    }

    public function isEmpty(): bool
    {
        return empty($this->wheres);
    }

    public function isSimple(): bool
    {
        if (count($this->wheres) === 1) {
            return array_key_exists('operator', $this->wheres[0]) === false
                || $this->wheres[0]['operator'] === '=';
        }

        return false;
    }

    public function getFields(): array
    {
        return array_values($this->fields);
    }
}
