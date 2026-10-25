<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Scenarios\Delete\DeleteRowsStep;
use Lapaliv\BulkUpsert\Scenarios\Delete\DispatchDeletedEventsStep;
use Lapaliv\BulkUpsert\Scenarios\Delete\DispatchDeletingEventsStep;
use Lapaliv\BulkUpsert\Scenarios\Shared\MarkNonexistentRowsAsSkippedStep;

/**
 * Deletes the rows from the database.
 *
 * The short algorithm contains the next steps:
 *
 * - Mark the rows that do not exist in the database as skipped;
 * - Fire the 'forceDeleting' event for each model when the rows are being force deleted;
 * - Fire the 'deleting' event for each model after the 'forceDeleting' event;
 * - Fire the 'forceDeletingMany' and 'deletingMany' events for all models
 *   after the 'deleting' event;
 * - Soft delete the rows by filling in their $deletedAtColumn,
 *   or delete them for real when either the model does not support soft deleting
 *   or the deletion is forced;
 * - Exit the algorithm if a certain condition is met, indicating that there was
 *   nothing to delete at all;
 * - Fire the 'deleted' event for each deleted model;
 * - Fire the 'forceDeleted' event for each model which has been force deleted;
 * - Fire the 'deletedMany' event for all models after the 'deleted' event;
 * - Fire the 'forceDeletedMany' event for all models after the 'deletedMany' event.
 *
 * @internal
 */
readonly class DeleteScenario
{
    /**
     * Create a new deleting scenario instance.
     *
     * @param \Lapaliv\BulkUpsert\Scenarios\Shared\MarkNonexistentRowsAsSkippedStep $markNonexistentRowsAsSkipped
     * @param \Lapaliv\BulkUpsert\Scenarios\Delete\DispatchDeletingEventsStep $dispatchDeletingEventsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Delete\DeleteRowsStep $deleteRowsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Delete\DispatchDeletedEventsStep $dispatchDeletedEventsStep
     */
    public function __construct(
        private MarkNonexistentRowsAsSkippedStep $markNonexistentRowsAsSkipped,
        private DispatchDeletingEventsStep $dispatchDeletingEventsStep,
        private DeleteRowsStep $deleteRowsStep,
        private DispatchDeletedEventsStep $dispatchDeletedEventsStep,
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
     * @param string|null $deletedAtColumn
     * @param bool $force
     *
     * @return void
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        array $dateFields,
        ?string $deletedAtColumn,
        bool $force,
    ): void
    {
        if (!$data->hasRows()) {
            return;
        }

        // The rows that are absent in the database cannot be deleted,
        // so they are marked as skipped before anything else happens.
        // The primary key is selected as well: the rows are deleted by it.
        $this->markNonexistentRowsAsSkipped->handle(
            $eloquent,
            $data,
            array_values(array_unique([...$data->getUniqueBy(), $eloquent->getKeyName()])),
            $deletedAtColumn,
            true,
        );

        // The rows which have not been found are not deleted, so neither the deleting
        // nor the deleted events are dispatched for them.
        foreach ($data->getRows() as $row) {
            if (!$row->getModel()->exists) {
                $row->setSkipDeleting(true);
            }
        }

        // Before deleting, it needs to define the final list of models that need to be deleted.
        // For this purpose, it uses events such as
        // 'forceDeleting', 'deleting', 'forceDeletingMany', 'deletingMany'.
        $this->dispatchDeletingEventsStep->handle($eloquent, $data, $eventDispatcher, $deletedAtColumn, $force);

        // The date is shared between the query and the events:
        // the listeners have to see the very value which has been written to the database.
        $deletedAt = Carbon::now();

        // Delete rows from the database.
        // There is nothing to report when every row has been skipped.
        $deleted = $this->deleteRowsStep->handle(
            $eloquent,
            $data,
            $dateFields,
            $deletedAtColumn,
            $force,
            $deletedAt,
        );

        if (!$deleted) {
            return;
        }

        // After deleting, it needs to fire events ending with '-ed' such as
        // 'deleted', 'forceDeleted', 'deletedMany', 'forceDeletedMany'.
        $this->dispatchDeletedEventsStep->handle(
            $eloquent,
            $data,
            $eventDispatcher,
            $deletedAtColumn,
            $force,
            $deletedAt,
        );
    }
}
