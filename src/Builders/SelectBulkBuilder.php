<?php

namespace Lapaliv\BulkUpsert\Builders;

use Lapaliv\BulkUpsert\Builders\Clauses\BuilderWhereClause;
use Lapaliv\BulkUpsert\Builders\Clauses\BulkBuilderWhereClause;

/**
 * Collects the parts of a SELECT statement.
 *
 * @internal
 */
class SelectBulkBuilder implements BulkBuilderWhereClause
{
    use BuilderWhereClause;

    /**
     * The name of the table which the rows are selected from.
     *
     * @var string|null
     */
    private ?string $table = null;

    /**
     * The columns which have to be selected.
     *
     * @var string[]
     */
    private array $columns = [];

    /**
     * Get the table which the rows are selected from.
     *
     * @return string|null
     *
     * @psalm-api
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Set the table which the rows are selected from.
     *
     * @param string $table
     *
     * @return $this
     *
     * @psalm-api
     */
    public function setTable(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Get the columns which have to be selected.
     *
     * @return string[]
     *
     * @psalm-api
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Set the columns which have to be selected.
     *
     * @param string[] $columns
     *
     * @return $this
     *
     * @psalm-api
     */
    public function setColumns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }
}
