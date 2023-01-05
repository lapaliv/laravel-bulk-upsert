<?php

namespace Lapaliv\BulkUpsert\DatabaseDrivers\Postgres;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Features\BulkAddWhereClauseToBuilderFeature;
use Lapaliv\BulkUpsert\Features\BulkKeyByFeature;

class BulkPostgresDriverSelectAffectedRowsFeature
{
    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param string[] $uniqueAttributes
     * @param string|null $primaryKeyName
     */
    public function __construct(
        protected Builder $builder,
        protected array   $uniqueAttributes,
        protected ?string  $primaryKeyName,
    )
    {
        //
    }

    /**
     * @param array<int, array<string, scalar>> $rows
     * @param array<int, array<string, scalar>> $insertedRows
     * @param string[] $columns
     * @return \stdClass[]
     */
    public function handle(array $rows, array $insertedRows, array $columns): array
    {
        $keyByFeature = new BulkKeyByFeature();

        $keyedRows = $keyByFeature->handle($rows, $this->uniqueAttributes);
        $keyedInsertedRows = $keyByFeature->handle($insertedRows, $this->uniqueAttributes);

        $builder = $this->builder->select($columns);

        $primaries = [];
        $needToSelect = [];

        foreach ($keyedRows as $key => $row) {
            if (array_key_exists($key, $keyedInsertedRows)
                && $this->primaryKeyName !== null
                && array_key_exists($this->primaryKeyName, $keyedInsertedRows[$key])
            ) {
                $primaries[] = $keyedInsertedRows[$key][$this->primaryKeyName];
            } else {
                $needToSelect[] = $row;
            }
        }

        if (empty($needToSelect) === false) {
            $addWhereClauseFeature = new BulkAddWhereClauseToBuilderFeature($this->uniqueAttributes);
            $addWhereClauseFeature->handle($builder, $needToSelect);
        }

        if (empty($primaries) === false) {
            $builder->orWhereIn(
                $this->primaryKeyName,
                $primaries
            );
        }

        return $builder->get()->toArray();
    }
}
