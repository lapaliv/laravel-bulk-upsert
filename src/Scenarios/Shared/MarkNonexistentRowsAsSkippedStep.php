<?php

namespace Lapaliv\BulkUpsert\Scenarios\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Builders\SelectExistingRowsQuery;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
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
     */
    public function __construct(
        private SelectExistingRowsQuery $selectExistingRowsQuery,
        private UniqueKeyGenerator $uniqueKeyGenerator,
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

            $this->mark($data, $nonexistent, $exists->values());
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
        BulkAccumulationEntity $result,
        BulkAccumulationEntity $nonExistent,
        Collection $exists,
    ): void
    {
        $mapIndexesAndKeys = [];

        foreach ($result->getRows() as $index => $row) {
            $key = $this->uniqueKeyGenerator->handle($row->getModel(), $result->getUniqueBy());
            $mapIndexesAndKeys[$key] = $index;
        }

        /** @var array<string, \Illuminate\Database\Eloquent\Model> $keyedSelected */
        $keyedSelected = $this->uniqueKeyGenerator->keyBy($exists, $nonExistent->getUniqueBy());

        foreach ($nonExistent->getRows() as $row) {
            $key = $this->uniqueKeyGenerator->handle($row->getModel(), $nonExistent->getUniqueBy());

            if (array_key_exists($key, $keyedSelected)) {
                foreach (array_keys($row->getModel()->getAttributes()) as $attribute) {
                    $keyedSelected[$key]->setAttribute(
                        $attribute,
                        $row->getModel()->getAttribute($attribute)
                    );
                }
                $row->setModel($keyedSelected[$key]);
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
