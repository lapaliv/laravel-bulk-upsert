<?php

namespace Lapaliv\BulkUpsert\Scenarios\Create;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Support\UniqueKeyGenerator;

/**
 * Replaces the models in the memory with the models which have been selected from the database.
 *
 * @internal
 */
readonly class MatchSelectedModelsStep
{
    /**
     * Create a new step instance.
     *
     * @param \Lapaliv\BulkUpsert\Support\UniqueKeyGenerator $uniqueKeyGenerator
     */
    public function __construct(
        private UniqueKeyGenerator $uniqueKeyGenerator,
    )
    {
        //
    }

    /**
     * Compare rows from $data with rows from the database,
     * and replace the former with the latter.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Illuminate\Support\Collection<int|string, \Illuminate\Database\Eloquent\Model> $existingRows
     *
     * @return void
     */
    public function handle(BulkAccumulationEntity $data, Collection $existingRows): void
    {
        $keyedExistingRows = $existingRows->keyBy(
            function (Model $model) use ($data): string {
                return $this->uniqueKeyGenerator->handle($model, $data->getUniqueBy());
            }
        );

        foreach ($data->getRows() as $index => $row) {
            $key = $this->uniqueKeyGenerator->handle($row->getModel(), $data->getUniqueBy());

            $existingRow = $keyedExistingRows->get($key);

            if ($existingRow !== null) {
                $row->setModel($existingRow);
            } else {
                $data->unsetRow((int) $index);
            }
        }
    }
}
