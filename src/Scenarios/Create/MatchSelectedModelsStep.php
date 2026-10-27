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
        /** @var array<string, \Illuminate\Database\Eloquent\Model> $keyedExistingRows */
        $keyedExistingRows = [];

        foreach ($existingRows as $existingRow) {
            $keyedExistingRows[$this->uniqueKeyGenerator->handle($existingRow, $data->getUniqueBy())] = $existingRow;
        }

        foreach ($data->getRows() as $index => $row) {
            $key = $this->uniqueKeyGenerator->handle($row->getModel(), $data->getUniqueBy());

            $existingRow = $keyedExistingRows[$key] ?? null;

            if ($existingRow !== null) {
                $row->setModel($existingRow);
                // Recorded before any listener, which may change `exists` later.
                $row->markMatchedInDatabase();
            } else {
                $data->unsetRow((int) $index);
            }
        }
    }
}
