<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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
        Model $eloquent,
        BulkAccumulationEntity $data,
        array $selectColumns,
        ?string $deletedAtColumn,
        bool $withTrashed,
    ): void {
        $nonexistent = new BulkAccumulationEntity(uniqueBy: $data->uniqueBy);

        foreach ($data->rows as $accumulationRow) {
            if (!$accumulationRow->model->exists) {
                $nonexistent->rows[] = $accumulationRow;
            }
        }

        if (!empty($nonexistent->rows)) {
            $this->mark(
                $data,
                $nonexistent,
                $this->selectExistingRowsFeature->handle(
                    $eloquent,
                    $nonexistent->getNotSkippedModels(),
                    $data->uniqueBy,
                    $selectColumns,
                    $deletedAtColumn,
                    $withTrashed,
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

        /** @var array<string, Model> $keyedSelected */
        $keyedSelected = $this->keyByFeature->handle($exists, $nonExistent->uniqueBy);

        foreach ($nonExistent->rows as $row) {
            $key = $this->getUniqueKeyFeature->handle($row->model, $nonExistent->uniqueBy);

            if (array_key_exists($key, $keyedSelected)) {
                foreach (array_keys($row->getModel()->getAttributes()) as $attribute) {
                    $keyedSelected[$key]->setAttribute(
                        $attribute,
                        $row->getModel()->getAttribute($attribute)
                    );
                }
                $row->setModel($keyedSelected[$key]);
            } else {
                $row->skipUpdating = true;
            }

            if (array_key_exists($key, $mapIndexesAndKeys)) {
                $result->rows[$mapIndexesAndKeys[$key]] = $row;
            }
        }
    }
}
