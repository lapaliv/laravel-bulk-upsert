<?php

namespace Lapaliv\BulkUpsert\Builders;

use Lapaliv\BulkUpsert\Builders\Clauses\BuilderCase;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderWhere;

class UpdateBuilder
{
    use BuilderWhere;

    private string $table;
    private array $sets = [];
    private ?int $limit = null;

    public function getTable(): string
    {
        return $this->table;
    }

    public function table(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    public function getSets(): array
    {
        return $this->sets;
    }

    public function set(string $field, BuilderCase|int|float|string|null|bool $value): static
    {
        $this->sets[$field] = $value;

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
}
