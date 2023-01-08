<?php

namespace Lapaliv\BulkUpsert\Database\Drivers;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;
use Lapaliv\BulkUpsert\Database\Drivers\Common\BulkDatabaseDriverUpdateFeature;
use Lapaliv\BulkUpsert\Database\Drivers\Mysql\BulkMysqlDriverInsertFeature;
use Lapaliv\BulkUpsert\Database\Drivers\Mysql\BulkMysqlDriverSelectAffectedRowsFeature;
use Lapaliv\BulkUpsert\Database\Processors\MysqlProcessor;
use stdClass;
use Throwable;

class BulkMysqlBulkDatabaseDriver implements BulkDatabaseDriver
{
    private Builder $builder;
    private array $rows;
    private array $uniqueAttributes;
    private bool $hasIncrementing;
    private array $selectColumns;

    public function __construct(
        private MysqlProcessor $processor,
        private BulkMysqlDriverInsertFeature $mysqlDriverInsertFeature,
        private BulkMysqlDriverSelectAffectedRowsFeature $mysqlDriverSelectAffectedRowsFeature,
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
    public function insert(array $fields, bool $ignoring): ?int
    {
        return $this->mysqlDriverInsertFeature->handle(
            $this->builder->getConnection(),
            $this->builder->from,
            $fields,
            $this->rows,
            $ignoring,
            $this->hasIncrementing,
        );
    }

    /**
     * @return stdClass[]
     */
    public function selectAffectedRows(): array
    {
        return $this->mysqlDriverSelectAffectedRowsFeature->handle(
            $this->builder,
            $this->uniqueAttributes,
            $this->rows,
            $this->selectColumns,
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
