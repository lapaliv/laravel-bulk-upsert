<?php

namespace Lapaliv\BulkUpsert\Builders;

/**
 * Collects the parts of an INSERT statement.
 *
 * @internal
 */
class InsertBuilder
{
    /**
     * The name of the table which the rows are inserted into.
     *
     * @var string|null
     */
    private ?string $table = null;

    /**
     * The columns which have to be filled in.
     *
     * @var string[]
     */
    private array $columns = [];

    /**
     * The columns which have to be returned by the statement.
     *
     * @var string[]
     */
    private array $select = [];

    /**
     * The rows which have to be inserted.
     *
     * @var array<int|string, scalar[]>
     */
    private array $values = [];

    /**
     * Whether the rows with a unique conflict have to be skipped silently.
     *
     * @var bool
     */
    private bool $onConflictDoNothing = false;

    /**
     * Get the table which the rows are inserted into.
     *
     * @return string|null
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Set the table which the rows are inserted into.
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
     * Get the rows which have to be inserted.
     *
     * @return array<int|string, scalar[]>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Add one more row to the statement.
     *
     * @param scalar[] $row
     *
     * @return $this
     */
    public function addValue(array $row): static
    {
        $this->values[] = $row;

        return $this;
    }

    /**
     * Get the columns which have to be filled in.
     *
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Set the columns which have to be filled in.
     *
     * @param string[] $columns
     *
     * @return $this
     */
    public function setColumns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Set the columns which have to be returned by the statement.
     *
     * @param string[] $columns
     *
     * @return $this
     */
    public function setSelect(array $columns): static
    {
        $this->select = $columns;

        return $this;
    }

    /**
     * Get the columns which have to be returned by the statement.
     *
     * @return string[]
     */
    public function getSelect(): array
    {
        return $this->select;
    }

    /**
     * Determine whether the rows with a unique conflict have to be skipped.
     *
     * @return bool
     */
    public function doNothingAtConflict(): bool
    {
        return $this->onConflictDoNothing;
    }

    /**
     * Set whether the rows with a unique conflict have to be skipped.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function onConflictDoNothing(bool $value): static
    {
        $this->onConflictDoNothing = $value;

        return $this;
    }
}
