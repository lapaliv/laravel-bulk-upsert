<?php

namespace Lapaliv\BulkUpsert\DatabaseDrivers;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\DatabaseDrivers\Postgres\BulkPostgresDriverInsertFeature;
use Lapaliv\BulkUpsert\DatabaseDrivers\Postgres\BulkPostgresDriverSelectAffectedRowsFeature;
use Lapaliv\BulkUpsert\Features\BulkConvertStdClassCollectionToArrayCollectionFeature;

class BulkPostgresBulkDatabaseDriver implements BulkDatabaseDriver
{
    /**
     * @var array[]
     */
    private array $insertedRows = [];

    /**
     * @param \Illuminate\Database\Connection $connection
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param string $primaryKeyName
     * @param array<int, array<string, scalar>> $rows
     * @param string[] $uniqueAttributes
     * @param bool $hasIncrementing
     */
    public function __construct(
        private Connection $connection,
        private Builder    $builder,
        private string     $primaryKeyName,
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
        $feature = new BulkPostgresDriverInsertFeature(
            $this->connection,
            $this->builder->from,
            $this->uniqueAttributes,
            $this->primaryKeyName
        );

        $insertedRows = $feature->handle($fields, $this->rows, $ignoring);

        if (empty($insertedRows) === false) {
            $this->insertedRows = (new BulkConvertStdClassCollectionToArrayCollectionFeature())
                ->handle($insertedRows);

            if ($this->hasIncrementing) {
                reset($insertedRows);

                return current($insertedRows)->{$this->primaryKeyName} ?? null;
            }
        }

        return null;
    }

    /**
     * @param string[] $columns
     * @return \stdClass[]
     */
    public function selectAffectedRows(array $columns = ['*']): array
    {
        $feature = new BulkPostgresDriverSelectAffectedRowsFeature(
            $this->builder,
            $this->uniqueAttributes,
            $this->primaryKeyName
        );

        return $feature->handle($this->rows, $this->insertedRows, $columns);
    }
}