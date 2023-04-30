<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * @internal
 */
class MarkNonexistentRowsAsSkippedFeature
{
    public function __construct(
        private SelectExistingRowsFeature $selectExistingRowsFeature,
        private KeyByFeature $keyByFeature,
        private GetUniqueKeyFeature $getUniqueKeyFeature,
    ) {
        // Nothing
    }

    public function handle(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        array $selectColumns,
        ?string $deletedAtColumn,
    ): void {
        $nonexistent = new BulkAccumulationEntity($data->uniqueBy);

        foreach ($data->rows as $accumulationRow) {
            if ($accumulationRow->model->exists === false) {
                $nonexistent->rows[] = $accumulationRow;
            }
        }

        if (empty($nonexistent->rows) === false) {
            $this->mark(
                $data,
                $nonexistent,
                $this->selectExistingRowsFeature->handle(
                    $eloquent,
                    $nonexistent->getNotSkippedModels(),
                    $data->uniqueBy,
                    $selectColumns,
                    $deletedAtColumn,
                )
            );
        }
    }

    private function mark(
        BulkAccumulationEntity $result,
        BulkAccumulationEntity $nonExistent,
        Collection $exists,
    ): void {
        $mapIndexesAndKeys = [];

        foreach ($result->rows as $index => $row) {
            $key = $this->getUniqueKeyFeature->handle($row->model, $result->uniqueBy);
            $mapIndexesAndKeys[$key] = $index;
        }

        /** @var array<string, BulkModel> $keyedSelected */
        $keyedSelected = $this->keyByFeature->handle($exists, $nonExistent->uniqueBy);

        foreach ($nonExistent->rows as $row) {
            $key = $this->getUniqueKeyFeature->handle($row->model, $nonExistent->uniqueBy);

            if (array_key_exists($key, $keyedSelected)) {
                foreach ($row->model->getAttributes() as $attribute => $value) {
                    $keyedSelected[$key]->setAttribute(
                        $attribute,
                        $row->model->getAttribute($attribute)
                    );
                }
                $row->model = $keyedSelected[$key];
            } else {
                $row->skipUpdating = true;
            }

            if (array_key_exists($key, $mapIndexesAndKeys)) {
                $result->rows[$mapIndexesAndKeys[$key]] = $row;
            }
        }
    }
}
