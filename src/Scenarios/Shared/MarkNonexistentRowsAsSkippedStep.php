<?php

namespace Lapaliv\BulkUpsert\Scenarios\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Builders\SelectExistingRowsQuery;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Support\DatabaseKeyMatcher;
use Lapaliv\BulkUpsert\Support\UniqueKeyGenerator;

/**
 * Splits the rows into the existing and the nonexistent ones.
 *
 * The models of the existing rows are replaced with their database versions,
 * and the nonexistent rows are marked as skipped.
 *
 * @internal
 */
readonly class MarkNonexistentRowsAsSkippedStep
{
    /**
     * Create a new step instance.
     *
     * @param \Lapaliv\BulkUpsert\Builders\SelectExistingRowsQuery $selectExistingRowsQuery
     * @param \Lapaliv\BulkUpsert\Support\UniqueKeyGenerator $uniqueKeyGenerator
     * @param \Lapaliv\BulkUpsert\Support\DatabaseKeyMatcher $databaseKeyMatcher
     */
    public function __construct(
        private SelectExistingRowsQuery $selectExistingRowsQuery,
        private UniqueKeyGenerator $uniqueKeyGenerator,
        private DatabaseKeyMatcher $databaseKeyMatcher,
    )
    {
        //
    }

    /**
     * Select the rows whose models do not exist yet, and mark them by what has been found.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param string[] $selectColumns
     * @param string|null $deletedAtColumn
     * @param bool $withTrashed
     *
     * @return void
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        array $selectColumns,
        ?string $deletedAtColumn,
        bool $withTrashed,
    ): void
    {
        $nonexistent = new BulkAccumulationEntity(uniqueBy: $data->getUniqueBy());

        foreach ($data->getRows() as $accumulationRow) {
            if (!$accumulationRow->getModel()->exists) {
                $nonexistent->addRow($accumulationRow);
            }
        }

        if ($nonexistent->hasRows()) {
            /** @var \Illuminate\Support\Collection $notSkippedSavingModels */
            $notSkippedSavingModels = $data->getNotSkippedSavingModels();

            $exists = $this->selectExistingRowsQuery->handle(
                $eloquent,
                $notSkippedSavingModels,
                $data->getUniqueBy(),
                $selectColumns,
                $deletedAtColumn,
                $withTrashed,
            );

            $this->mark($eloquent, $data, $nonexistent, $exists->values());
        }
    }

    /**
     * Replace the model of each found row, and mark the rest of the rows as skipped.
     *
     * The attributes which the caller asked to write are copied onto the model from the
     * database, so that the update is able to see them as the changes.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $result
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $nonExistent
     * @param \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $exists
     *
     * @return void
     */
    private function mark(
        Model $eloquent,
        BulkAccumulationEntity $result,
        BulkAccumulationEntity $nonExistent,
        Collection $exists,
    ): void
    {
        $uniqueBy = $nonExistent->getUniqueBy();
        $mapIndexesAndKeys = [];

        foreach ($result->getRows() as $index => $row) {
            $key = $this->uniqueKeyGenerator->handle($row->getModel(), $result->getUniqueBy());
            $mapIndexesAndKeys[$key] = $index;
        }

        /** @var array<string, \Illuminate\Database\Eloquent\Model> $keyedSelected */
        $keyedSelected = $this->uniqueKeyGenerator->keyBy($exists, $uniqueBy);

        // The rows are matched in PHP first. The selected rows which no row of the data has
        // matched there may still be equal to a row by the rules of the database (a collation):
        // the database decides for them, so a found row is neither skipped nor inserted again.
        $usedKeys = [];
        /** @var array<int, \Illuminate\Database\Eloquent\Model> $unmatched */
        $unmatched = [];

        foreach ($result->getRows() as $row) {
            $usedKeys[$this->uniqueKeyGenerator->handle($row->getModel(), $uniqueBy)] = true;
        }

        foreach ($nonExistent->getRows() as $index => $row) {
            if (!array_key_exists($this->uniqueKeyGenerator->handle($row->getModel(), $uniqueBy), $keyedSelected)) {
                $unmatched[(int) $index] = $row->getModel();
            }
        }

        $leftovers = array_values(array_diff_key($keyedSelected, $usedKeys));
        $matchedByDatabase = $unmatched !== [] && $leftovers !== []
            ? $this->databaseKeyMatcher->match($eloquent, $uniqueBy, $unmatched, $leftovers)
            : [];

        foreach ($nonExistent->getRows() as $index => $row) {
            $key = $this->uniqueKeyGenerator->handle($row->getModel(), $uniqueBy);

            if (isset($matchedByDatabase[(int) $index])) {
                $selected = $matchedByDatabase[(int) $index];
                $stored = $selected->getAttributes();
                // The unique values stay the stored ones: the database has found the row by
                // them, and they are never written, so the model describes the stored row.
                $selected->setRawAttributes(array_replace(
                    $stored,
                    $row->getModel()->getAttributes(),
                    array_intersect_key($stored, array_flip($uniqueBy)),
                ));
                $row->setModel($selected);
                $row->markMatchedInDatabase();
            } elseif (array_key_exists($key, $keyedSelected)) {
                // The given values have already been through the mutators and the casts when
                // the input was turned into a model, so they are copied as raw attributes.
                // `setAttribute()` would run the mutators a second time (e.g. `new` → `new!!`).
                // The original attributes of the selected model are kept: they describe the
                // stored row, so the changes and the stored key are resolved against them.
                $selected = $keyedSelected[$key];
                $selected->setRawAttributes(
                    array_replace($selected->getAttributes(), $row->getModel()->getAttributes())
                );
                $row->setModel($selected);
                // Recorded before any listener, which may change `exists` later.
                $row->markMatchedInDatabase();
            } else {
                $row->setSkipUpdating(true);
            }

            if (array_key_exists($key, $mapIndexesAndKeys)) {
                $rows = $result->getRows();
                $rows[$mapIndexesAndKeys[$key]] = $row;
                $result->setRows($rows);
            }
        }
    }
}
