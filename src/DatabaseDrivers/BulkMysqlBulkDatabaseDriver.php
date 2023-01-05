<?php

namespace Lapaliv\BulkUpsert\DatabaseDrivers;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;
use Lapaliv\BulkUpsert\DatabaseDrivers\Mysql\BulkMysqlDriverInsertFeature;
use Lapaliv\BulkUpsert\DatabaseDrivers\Mysql\BulkMysqlDriverSelectAffectedRowsFeature;

class BulkMysqlBulkDatabaseDriver implements BulkDatabaseDriver
{
    private string $connectionName;
    private Builder $builder;
    private array $rows;
    private array $uniqueAttributes;
    private bool $hasIncrementing;
    private array $selectColumns;

    /**
     * @param string[] $fields
     * @param bool $ignoring
     * @return int|null
     * @throws \Throwable
     */
    public function insert(array $fields, bool $ignoring): ?int
    {
        $feature = new BulkMysqlDriverInsertFeature(
            $this->builder->getConnection(),
            $this->connectionName,
            $this->builder->from,
            $this->hasIncrementing,
        );

        return $feature->handle($fields, $this->rows, $ignoring);
    }

    /**
     * @return \stdClass[]
     */
    public function selectAffectedRows(): array
    {
        return (new BulkMysqlDriverSelectAffectedRowsFeature($this->builder, $this->uniqueAttributes))
            ->handle($this->rows, $this->selectColumns);
    }

    public function setConnectionName(string $name): static
    {
        $this->connectionName = $name;

        return $this;
    }

    public function setBuilder(Builder $builder): static
    {
        $this->builder = $builder;

        return $this;
    }

    public function setRows(array $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function setUniqueAttributes(array $uniqueAttributes): static
    {
        $this->uniqueAttributes = $uniqueAttributes;

        return $this;
    }

    public function setHasIncrementing(bool $value): static
    {
        $this->hasIncrementing = $value;

        return $this;
    }

    public function setPrimaryKeyName(?string $name): static
    {
        return $this;
    }

    public function setSelectColumns(array $columns): static
    {
        $this->selectColumns = $columns;

        return $this;
    }
}
