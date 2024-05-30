<?php

namespace Lapaliv\BulkUpsert\Builders;

use Lapaliv\BulkUpsert\Builders\Clauses\BuilderWhere;
use Lapaliv\BulkUpsert\Contracts\BulkBuilderWhereClause;

/**
 * @internal
 */
class SelectBulkBuilder implements BulkBuilderWhereClause
{
    use BuilderWhere;

    private ?string $from = null;

    /**
     * @var string[]
     */
    private array $columns = [];

    /**
     * @return string|null
     *
     * @psalm-api
     */
    public function getFrom(): ?string
    {
        return $this->from;
    }

    /**
     * @param string $table
     *
     * @return $this
     *
     * @psalm-api
     */
    public function from(string $table): static
    {
        $this->from = $table;

        return $this;
    }

    /**
     * @return string[]
     *
     * @psalm-api
     */
    public function getSelect(): array
    {
        return $this->columns;
    }

    /**
     * @param string[] $columns
     *
     * @return $this
     *
     * @psalm-api
     */
    public function select(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }
}
