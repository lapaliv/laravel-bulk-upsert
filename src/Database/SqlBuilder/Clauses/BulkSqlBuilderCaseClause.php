<?php

namespace Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\CaseClause\BulkSqlBuilderCaseClauseBody;

class BulkSqlBuilderCaseClause
{
    /**
     * @var BulkSqlBuilderCaseClauseBody[]
     */
    private array $when = [];
    private ?string $condition;
    private mixed $else;
    private string $elseField;

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function condition(string $field): static
    {
        $this->condition = $field;

        return $this;
    }

    public function getBody(): array
    {
        return $this->when;
    }

    public function newBody(): BulkSqlBuilderCaseClauseBody
    {
        $when = new BulkSqlBuilderCaseClauseBody();
        $this->when[] = $when;

        return $when;
    }

    public function hasElse(): bool
    {
        return isset($this->else);
    }

    public function getElse(): mixed
    {
        return $this->else;
    }

    public function else(mixed $value): static
    {
        $this->else = $value;

        return $this;
    }

    public function hasElseField(): bool
    {
        return isset($this->elseField);
    }

    public function getElseField(): string
    {
        return $this->elseField;
    }

    public function elseField(string $field): static
    {
        $this->elseField = $field;

        return $this;
    }

    public function isSimple(): bool
    {
        if (isset($this->condition)) {
            return false;
        }

        if (count($this->getWhenUniqueFields()) > 1) {
            return false;
        }

        foreach ($this->when as $when) {
            if ($when->isEmpty()) {
                continue;
            }

            if ($when->isSimple() === false) {
                return false;
            }
        }

        return true;
    }

    public function getWhenUniqueFields(): array
    {
        $result = [];

        foreach ($this->when as $when) {
            if ($when->isEmpty() === false) {
                foreach ($when->getFields() as $field) {
                    $result[$field] = $field;
                }
            }
        }

        return array_values($result);
    }
}
