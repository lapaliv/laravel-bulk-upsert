<?php

namespace Lapaliv\BulkUpsert\Builders;

use Lapaliv\BulkUpsert\Builders\Clauses\BuilderWhereClause;
use Lapaliv\BulkUpsert\Builders\Clauses\BulkBuilderWhereClause;

/**
 * Collects the parts of a DELETE statement.
 *
 * @internal
 */
class DeleteBulkBuilder implements BulkBuilderWhereClause
{
    use BuilderWhereClause;

    /**
     * The name of the table which the rows are deleted from.
     *
     * @var string
     */
    private string $table = '';

    /**
     * The maximum number of the rows which can be deleted.
     *
     * @var int
     */
    private int $limit = 0;

    /**
     * Set the table which the rows are deleted from.
     *
     * @param string $table
     *
     * @return $this
     */
    public function setTable(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Set the maximum number of the rows which can be deleted.
     *
     * @param int $limit
     *
     * @return $this
     */
    public function setLimit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

}
