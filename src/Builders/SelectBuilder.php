<?php

namespace Lapaliv\BulkUpsert\Builders;

use Lapaliv\BulkUpsert\Builders\Clauses\BuilderWhere;
use Lapaliv\BulkUpsert\Contracts\BuilderWhereClause;

/**
 * @internal
 */
class SelectBuilder implements BuilderWhereClause
{
    use BuilderWhere;

    private ?string $from = null;

    /**
     * @var string[]
     */
    private array $columns = [];

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function from(string $table): static
    {
        $this->from = $table;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getSelect(): array
    {
        return $this->columns;
    }

    /**
     * @param string[] $columns
     *
     * @return $this
     */
    public function select(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function reset(): static
    {
        $this->from = null;
        $this->columns = [];
        $this->wheres = [];
        $this->fields = [];

        return $this;
    }
}
