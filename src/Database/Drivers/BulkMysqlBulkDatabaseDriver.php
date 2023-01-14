<?php

namespace Lapaliv\BulkUpsert\Database\Drivers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;
use Lapaliv\BulkUpsert\Database\Drivers\Common\BulkDatabaseDriverUpdateFeature;
use Lapaliv\BulkUpsert\Database\Drivers\Mysql\BulkMysqlDriverInsertFeature;
use Lapaliv\BulkUpsert\Database\Drivers\Mysql\BulkMysqlDriverSelectAffectedRowsFeature;
use Lapaliv\BulkUpsert\Database\Processors\MysqlProcessor;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderInsert;
use stdClass;
use Throwable;

class BulkMysqlBulkDatabaseDriver implements BulkDatabaseDriver
{
    private Builder $builder;
    private array $rows;
    private array $uniqueAttributes;
    private bool $hasIncrementing;
    private array $selectColumns;

    private ?int $lastInsertedId = null;

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
     * @param ConnectionInterface $connection
     * @param BulkSqlBuilderInsert $sqlBuilder
     * @return bool
     */
    public function insert(ConnectionInterface $connection, BulkSqlBuilderInsert $sqlBuilder): bool
    {
        ['sql' => $sql, 'bindings' => $bindings] = $this->processor->insert($sqlBuilder);

        $connection->beginTransaction();

        try {
            $this->lastInsertedId = $connection->selectOne($sql, $bindings);
            $connection->commit();

            return true;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
//        return $this->mysqlDriverInsertFeature->handle(
//            $this->builder->getConnection(),
//            $this->builder->from,
//            $this->rows,
//            $ignoring,
//            $this->hasIncrementing,
//        );
    }

    /**
     * @return stdClass[]
     */
    public function selectAffectedRows(ConnectionInterface $connection, array $rows): array
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
