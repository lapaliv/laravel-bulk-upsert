<?php

namespace Lapaliv\BulkUpsert\Builders;

use Lapaliv\BulkUpsert\Builders\Clauses\BuilderCase;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderWhere;
use Lapaliv\BulkUpsert\Builders\Clauses\Case\BuilderCaseWhen;
use Lapaliv\BulkUpsert\Contracts\BuilderWhereClause;

class UpdateBuilder implements BuilderWhereClause
{
    use BuilderWhere;

    private ?string $table = null;
    private ?int $limit = null;

    /**
     * @var array<string, BuilderCase>
     */
    private array $sets = [];

    public function getTable(): string
    {
        return $this->table;
    }

    public function table(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @return array<string, BuilderCase>
     */
    public function getSets(): array
    {
        return $this->sets;
    }

    /**
     * @param string $field
     * @param array<string, scalar[]|scalar> $filters
     * @param int|float|string|bool|null $value
     * @return $this
     */
    public function addSet(string $field, array $filters, int|float|string|null|bool $value): static
    {
        $this->sets[$field] ??= (new BuilderCase())->else(new BuilderRawExpression($field));

        $when = new BuilderCaseWhen();

        foreach ($filters as $filterKey => $filterValue) {
            if (is_array($filterValue)) {
                $when->whereIn($filterKey, $filterValue);
            } else {
                $when->where($filterKey, '=', $filterValue);
            }
        }

        $when->then($value);
        $this->sets[$field]->addWhenThen($when);

        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function reset(): static
    {
        $this->table = null;
        $this->limit = null;
        $this->sets = [];
        $this->wheres = [];
        $this->fields = [];

        return $this;
    }
}
