<?php

namespace Lapaliv\BulkUpsert\Database\Drivers;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;
use Lapaliv\BulkUpsert\Database\Drivers\Common\BulkDatabaseDriverUpdateFeature;
use Lapaliv\BulkUpsert\Database\Drivers\Postgres\BulkPostgresDriverInsertFeature;
use Lapaliv\BulkUpsert\Database\Drivers\Postgres\BulkPostgresDriverSelectAffectedRowsFeature;
use Lapaliv\BulkUpsert\Database\Processors\PostgresProcessor;
use Lapaliv\BulkUpsert\Features\BulkConvertArrayOfObjectsToScalarArraysFeature;
use stdClass;
use Throwable;

class BulkPostgresBulkDatabaseDriver implements BulkDatabaseDriver
{
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

    public function __construct(
        private PostgresProcessor $processor,
        private BulkPostgresDriverInsertFeature $postgresDriverInsertFeature,
        private BulkConvertArrayOfObjectsToScalarArraysFeature $convertArrayOfObjectsToScalarArraysFeature,
        private BulkPostgresDriverSelectAffectedRowsFeature $postgresDriverSelectAffectedRowsFeature,
        private BulkDatabaseDriverUpdateFeature $databaseDriverUpdateFeature,
    )
    {
        //
    }

    /**
     * @param string[] $fields
     * @param bool $ignoring
     * @return int|null
     * @throws Throwable
     */
    public function insert(bool $ignoring): bool
    {
        $insertedRows = $this->postgresDriverInsertFeature->handle(
            $this->builder->getConnection(),
            $this->builder->from,
            $this->rows,
            $ignoring,
            $this->selectColumns,
        );

        if (empty($insertedRows) === false) {
            $this->insertedRows = $this->convertArrayOfObjectsToScalarArraysFeature->handle($insertedRows);

            if ($this->hasIncrementing && $this->primaryKeyName !== null) {
                reset($insertedRows);

                return current($insertedRows)->{$this->primaryKeyName} ?? null;
            }
        }

        return null;
    }

    /**
     * @return stdClass[]
     */
    public function selectAffectedRows(): array
    {
        return $this->postgresDriverSelectAffectedRowsFeature->handle(
            $this->builder,
            $this->uniqueAttributes,
            $this->rows,
            $this->insertedRows,
            $this->selectColumns,
            $this->primaryKeyName,
        );
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

    public function update(): bool
    {
        $result = $this->databaseDriverUpdateFeature->handle(
            $this->processor,
            $this->builder->getConnection(),
            $this->builder->from,
            $this->uniqueAttributes,
            $this->rows,
        );

        return $result > 0;
    }
}
