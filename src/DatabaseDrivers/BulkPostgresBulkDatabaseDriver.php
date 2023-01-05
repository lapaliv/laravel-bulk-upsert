<?php

namespace Lapaliv\BulkUpsert\DatabaseDrivers;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;
use Lapaliv\BulkUpsert\DatabaseDrivers\Postgres\BulkPostgresDriverInsertFeature;
use Lapaliv\BulkUpsert\DatabaseDrivers\Postgres\BulkPostgresDriverSelectAffectedRowsFeature;
use Lapaliv\BulkUpsert\Features\BulkConvertStdClassCollectionToArrayCollectionFeature;

class BulkPostgresBulkDatabaseDriver implements BulkDatabaseDriver
{
    private string $connectionName;
    private Builder $builder;
    private array $rows;
    private array $uniqueAttributes;
    private bool $hasIncrementing;
    private ?string $primaryKeyName;
    private array $selectColumns;

    /**
     * @var array[]
     */
    private array $insertedRows = [];

    /**
     * @param string[] $fields
     * @param bool $ignoring
     * @return int|null
     * @throws \Throwable
     */
    public function insert(array $fields, bool $ignoring): ?int
    {
        $feature = new BulkPostgresDriverInsertFeature(
            $this->builder->getConnection(),
            $this->connectionName,
            $this->builder->from,
            $this->selectColumns,
        );

        $insertedRows = $feature->handle($fields, $this->rows, $ignoring);

        if (empty($insertedRows) === false) {
            $this->insertedRows = (new BulkConvertStdClassCollectionToArrayCollectionFeature())
                ->handle($insertedRows);

            if ($this->hasIncrementing && $this->primaryKeyName !== null) {
                reset($insertedRows);

                return current($insertedRows)->{$this->primaryKeyName} ?? null;
            }
        }

        return null;
    }

    /**
     * @return \stdClass[]
     */
    public function selectAffectedRows(): array
    {
        $feature = new BulkPostgresDriverSelectAffectedRowsFeature(
            $this->builder,
            $this->uniqueAttributes,
            $this->primaryKeyName
        );

        return $feature->handle(
            $this->rows,
            $this->insertedRows,
            $this->selectColumns,
        );
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
        $this->primaryKeyName = $name;

        return $this;
    }

    public function setSelectColumns(array $columns): static
    {
        $this->selectColumns = $columns;

        return $this;
    }
}
