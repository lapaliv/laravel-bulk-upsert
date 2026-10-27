<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Scenarios\Shared\MarkNonexistentRowsAsSkippedStep;
use Lapaliv\BulkUpsert\Scenarios\Upsert\CreateNonexistentRowsStep;
use Lapaliv\BulkUpsert\Support\ReturnedModels;

/**
 * Inserts the rows that are absent in the database and updates the rest of them.
 *
 * The short algorithm contains the next steps:
 *
 * - Split the rows into the existing and the nonexistent ones;
 * - Insert the nonexistent rows by running the whole creating scenario for them;
 * - Give the rows that have not been created back to the updating scenario:
 *   they had appeared in the database in between the selection and the insert;
 * - Update the existing rows by running the whole updating scenario for them.
 *
 * Both nested scenarios fire their own events, so a row goes through either
 * the creating or the updating set of events, never through both of them.
 *
 * @internal
 */
readonly class UpsertScenario
{
    /**
     * Create a new upserting scenario instance.
     *
     * @param \Lapaliv\BulkUpsert\Scenarios\Shared\MarkNonexistentRowsAsSkippedStep $markNonexistentRowsAsSkipped
     * @param \Lapaliv\BulkUpsert\Scenarios\Upsert\CreateNonexistentRowsStep $createNonexistentRowsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\UpdateScenario $updateScenario
     */
    public function __construct(
        private MarkNonexistentRowsAsSkippedStep $markNonexistentRowsAsSkipped,
        private CreateNonexistentRowsStep $createNonexistentRowsStep,
        private UpdateScenario $updateScenario,
    )
    {
        //
    }

    /**
     * Run the scenario.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string[] $dateFields
     * @param string[] $selectColumns
     * @param string|null $deletedAtColumn
     * @param bool $withTrashed
     * @param \Lapaliv\BulkUpsert\Support\ReturnedModels|null $returned Collects the rows to give back.
     *
     * @return void
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
        bool $withTrashed,
        ?ReturnedModels $returned = null,
    ): void
    {
        if (!$data->hasRows()) {
            return;
        }

        // The rows that are absent in the database cannot be updated,
        // so they are marked as skipped and handed over to the creating scenario.
        $this->markNonexistentRowsAsSkipped->handle($eloquent, $data, $selectColumns, $deletedAtColumn, $withTrashed);

        $this->createNonexistentRowsStep->handle(
            $data,
            $eventDispatcher,
            $dateFields,
            $selectColumns,
            $deletedAtColumn,
            $returned,
        );

        // The updating scenario marks the nonexistent rows as skipped once again,
        // which is what excludes the rows that have just been created from the update.
        $this->updateScenario->handle(
            $eloquent,
            $data,
            $eventDispatcher,
            $dateFields,
            $selectColumns,
            $deletedAtColumn,
            $withTrashed,
            $returned,
        );
    }
}
