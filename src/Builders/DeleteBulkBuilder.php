<?php

namespace Lapaliv\BulkUpsert\Builders;

use Lapaliv\BulkUpsert\Builders\Clauses\BuilderWhere;
use Lapaliv\BulkUpsert\Contracts\BulkBuilderWhereClause;

class DeleteBulkBuilder implements BulkBuilderWhereClause
{
    use BuilderWhere;

    private string $from;
    private int $limit;

    public function from(string $table): static
    {
        $this->from = $table;

        return $this;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }
}
