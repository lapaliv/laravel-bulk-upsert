<?php

namespace Lapaliv\BulkUpsert\DatabaseDrivers;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\DatabaseDrivers\Mysql\BulkMysqlDriverInsertFeature;
use Lapaliv\BulkUpsert\DatabaseDrivers\Mysql\BulkMysqlDriverSelectAffectedRowsFeature;

class BulkMysqlBulkDatabaseDriver implements BulkDatabaseDriver
{
    /**
     * @param \Illuminate\Database\Connection $connection
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param array<int, array<string, scalar>> $rows
     * @param string[] $uniqueAttributes
     * @param bool $hasIncrementing
     */
    public function __construct(
        private Connection $connection,
        private Builder    $builder,
        private array      $rows,
        private array      $uniqueAttributes,
        private bool       $hasIncrementing,
    )
    {
        //
    }

    /**
     * @param string[] $fields
     * @param bool $ignoring
     * @return int|null
     * @throws \Throwable
     */
    public function insert(array $fields, bool $ignoring): ?int
    {
        $feature = new BulkMysqlDriverInsertFeature(
            $this->connection,
            $this->builder->from,
            $this->hasIncrementing,
        );

        return $feature->handle($fields, $this->rows, $ignoring);
    }

    /**
     * @param string[] $columns
     * @return \stdClass[]
     */
    public function selectAffectedRows(array $columns = ['*']): array
    {
        return (new BulkMysqlDriverSelectAffectedRowsFeature($this->builder, $this->uniqueAttributes))
            ->handle($this->rows, $columns);
    }
}