<?php

namespace Lapaliv\BulkUpsert\Scenarios\Create;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Support\DatabaseKeyMatcher;
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
        private DatabaseKeyMatcher $databaseKeyMatcher,
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

        // Matched in PHP first; the database decides for the rest (a collation may find the
        // stored row equal to the input though PHP does not).
        $usedKeys = [];
        /** @var array<int, \Illuminate\Database\Eloquent\Model> $unmatched */
        $unmatched = [];

        foreach ($data->getRows() as $index => $row) {
            $key = $this->uniqueKeyGenerator->handle($row->getModel(), $data->getUniqueBy());

            if (isset($keyedExistingRows[$key])) {
                $usedKeys[$key] = true;
            } else {
                $unmatched[(int) $index] = $row->getModel();
            }
        }

        $leftovers = array_values(array_diff_key($keyedExistingRows, $usedKeys));
        $first = $existingRows->first();
        $matchedByDatabase = $unmatched !== [] && $leftovers !== [] && $first instanceof Model
            ? $this->databaseKeyMatcher->match($first, $data->getUniqueBy(), $unmatched, $leftovers)
            : [];

        foreach ($data->getRows() as $index => $row) {
            $key = $this->uniqueKeyGenerator->handle($row->getModel(), $data->getUniqueBy());

            $existingRow = $matchedByDatabase[(int) $index] ?? $keyedExistingRows[$key] ?? null;

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
