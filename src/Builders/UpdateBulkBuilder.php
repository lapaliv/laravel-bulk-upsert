<?php

namespace Lapaliv\BulkUpsert\Builders;

use LogicException;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderCase;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderWhereClause;
use Lapaliv\BulkUpsert\Builders\Clauses\BulkBuilderWhereClause;
use Lapaliv\BulkUpsert\Builders\Clauses\Case\BuilderCaseWhen;

/**
 * Collects the parts of an UPDATE statement.
 *
 * @internal
 */
class UpdateBulkBuilder implements BulkBuilderWhereClause
{
    use BuilderWhereClause;

    /**
     * The name of the table which the rows are updated in.
     *
     * @var string|null
     */
    private ?string $table = null;

    /**
     * The maximum number of the rows which can be updated.
     *
     * @var int|null
     */
    private ?int $limit = null;

    /**
     * The new value of each column, keyed by the name of the column.
     *
     * @var array<string, \Lapaliv\BulkUpsert\Builders\Clauses\BuilderCase|scalar|null>
     */
    private array $sets = [];

    /**
     * Get the table which the rows are updated in.
     *
     * @return string|null
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Set the table which the rows are updated in.
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

    /**
     * Get the new value of each column.
     *
     * @return array<string, \Lapaliv\BulkUpsert\Builders\Clauses\BuilderCase|scalar|null>
     */
    public function getSets(): array
    {
        return $this->sets;
    }

    /**
     * Add a new value which is only applied to the rows matching the given filters.
     *
     * The column keeps a CASE expression. Each call adds one more WHEN branch to it,
     * and the ELSE branch leaves the old value of the column.
     *
     * @param string $field
     * @param array<string, scalar|scalar[]> $filters The values which the rows are matched by.
     * @param bool|float|int|string|null $value
     *
     * @return $this
     */
    public function addSet(string $field, array $filters, int|float|string|null|bool $value): static
    {
        $this->sets[$field] ??= (new BuilderCase())->setElse(new BuilderRawExpression($field));
        $set = $this->sets[$field];

        if (!$set instanceof BuilderCase) {
            throw new LogicException(
                sprintf('Cannot conditionally set the "%s" field: it already has a simple value set by addSimpleSet().', $field)
            );
        }

        $when = new BuilderCaseWhen();

        foreach ($filters as $filterKey => $filterValue) {
            if (is_array($filterValue)) {
                $when->whereIn($filterKey, $filterValue);
            } else {
                $when->where($filterKey, '=', $filterValue);
            }
        }

        $when->setThen($value);
        $set->addWhenThen($when);

        return $this;
    }

    /**
     * Set the same new value of the column for all the rows of the statement.
     *
     * @param string $field
     * @param bool|float|int|string|null $value
     *
     * @return $this
     */
    public function addSimpleSet(string $field, int|float|string|null|bool $value): static
    {
        $this->sets[$field] = $value;

        return $this;
    }

    /**
     * Get the maximum number of the rows which can be updated.
     *
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Set the maximum number of the rows which can be updated.
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
}
