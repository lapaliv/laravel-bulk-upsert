<?php

namespace Lapaliv\BulkUpsert\Builders;

use Lapaliv\BulkUpsert\Builders\Clauses\BuilderWhere;

class SelectBuilder
{
    use BuilderWhere;

    private string $from;
    private array $columns = [];

    public function getFrom(): string
    {
        return $this->from;
    }

    public function from(string $table): static
    {
        $this->from = $table;
        return $this;
    }

    public function getSelect(): array
    {
        return $this->columns;
    }

    public function select(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }
}
