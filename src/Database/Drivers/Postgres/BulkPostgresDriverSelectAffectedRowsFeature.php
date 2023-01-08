<?php

namespace Lapaliv\BulkUpsert\Database\Drivers\Postgres;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Features\BulkAddWhereClauseToBuilderFeature;
use Lapaliv\BulkUpsert\Features\BulkKeyByFeature;
use stdClass;

class BulkPostgresDriverSelectAffectedRowsFeature
{
    public function __construct(
        private BulkKeyByFeature $keyByFeature,
        private BulkAddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
    )
    {
        //
    }

    /**
     * @param array<int, array<string, scalar>> $rows
     * @param array<int, array<string, scalar>> $insertedRows
     * @param string[] $columns
     * @return stdClass[]
     */
    public function handle(
        Builder $builder,
        array $uniqueAttributes,
        array $rows,
        array $insertedRows,
        array $columns,
        ?string $primaryKeyName
    ): array
    {
        $keyedRows = $this->keyByFeature->handle($rows, $uniqueAttributes);
        $keyedInsertedRows = $this->keyByFeature->handle($insertedRows, $uniqueAttributes);

        $builder->select($columns);

        $primaries = [];
        $needToSelect = [];

        foreach ($keyedRows as $key => $row) {
            if (array_key_exists($key, $keyedInsertedRows)
                && $primaryKeyName !== null
                && array_key_exists($primaryKeyName, $keyedInsertedRows[$key])
            ) {
                $primaries[] = $keyedInsertedRows[$key][$primaryKeyName];
            } else {
                $needToSelect[] = $row;
            }
        }

        if (empty($needToSelect) === false) {
            $this->addWhereClauseToBuilderFeature->handle($builder, $uniqueAttributes, $needToSelect);
        }

        if (empty($primaries) === false) {
            $builder->orWhereIn(
                $primaryKeyName,
                $primaries
            );
        }

        return $builder->get()->toArray();
    }
}
