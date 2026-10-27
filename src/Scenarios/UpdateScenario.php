<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Scenarios\Shared\MarkNonexistentRowsAsSkippedStep;
use Lapaliv\BulkUpsert\Scenarios\Shared\TouchRelationsStep;
use Lapaliv\BulkUpsert\Scenarios\Update\DispatchDeletingAndRestoringEventsStep;
use Lapaliv\BulkUpsert\Scenarios\Update\DispatchSavingEventsStep;
use Lapaliv\BulkUpsert\Scenarios\Update\DispatchUpdatedEventsStep;
use Lapaliv\BulkUpsert\Scenarios\Update\DispatchUpdatingEventsStep;
use Lapaliv\BulkUpsert\Scenarios\Update\SyncChangesStep;
use Lapaliv\BulkUpsert\Scenarios\Update\SyncOriginalStep;
use Lapaliv\BulkUpsert\Scenarios\Update\UpdateRowsStep;
use Lapaliv\BulkUpsert\Support\ReturnedModels;

/**
 * Updates the rows in the database.
 *
 * The short algorithm contains the next steps:
 *
 * - Mark the rows that do not exist in the database as skipped;
 * - Fire the 'saving' event for each model at the start of working;
 * - Fire the 'savingMany' event for all models after the 'saving' event;
 * - Fire the 'updating' event for each not skipped model after the 'savingMany' event;
 * - Fire the 'updatingMany' event for all these models after the 'updating' event;
 * - Fire the 'deleting' event for each model whose $deletedAtColumn has just been filled in;
 * - Fire the 'restoring' event for each model whose $deletedAtColumn has just been cleared;
 * - Fire the 'deletingMany' and 'restoringMany' events for the corresponding models;
 * - Update rows in the database;
 * - Exit the algorithm if a certain condition is met, indicating that there is no need
 *   to keep the changes of the models and to fire the events ending with '-ed';
 * - Remember the changes of each model, so that the listeners are able to read them;
 * - Fire the 'updated' event for each updated model;
 * - Fire the 'deleted' event for each model which has been soft deleted;
 * - Fire the 'restored' event for each model which has been restored;
 * - Fire the 'saved' event for each model;
 * - Fire the 'updatedMany', 'deletedMany', 'restoredMany' and 'savedMany' events
 *   for the corresponding models.
 *
 * @internal
 */
readonly class UpdateScenario
{
    /**
     * Create a new updating scenario instance.
     *
     * @param \Lapaliv\BulkUpsert\Scenarios\Shared\MarkNonexistentRowsAsSkippedStep $markNonexistentRowsAsSkipped
     * @param \Lapaliv\BulkUpsert\Scenarios\Update\DispatchSavingEventsStep $dispatchSavingEventsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Update\DispatchUpdatingEventsStep $dispatchUpdatingEventsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Update\DispatchDeletingAndRestoringEventsStep $dispatchDeletingAndRestoringEventsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Update\UpdateRowsStep $updateRowsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Update\SyncChangesStep $syncChangesStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Update\DispatchUpdatedEventsStep $dispatchUpdatedEventsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Shared\TouchRelationsStep $touchRelationsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Update\SyncOriginalStep $syncOriginalStep
     */
    public function __construct(
        private MarkNonexistentRowsAsSkippedStep $markNonexistentRowsAsSkipped,
        private DispatchSavingEventsStep $dispatchSavingEventsStep,
        private DispatchUpdatingEventsStep $dispatchUpdatingEventsStep,
        private DispatchDeletingAndRestoringEventsStep $dispatchDeletingAndRestoringEventsStep,
        private UpdateRowsStep $updateRowsStep,
        private SyncChangesStep $syncChangesStep,
        private DispatchUpdatedEventsStep $dispatchUpdatedEventsStep,
        private TouchRelationsStep $touchRelationsStep,
        private SyncOriginalStep $syncOriginalStep,
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
        // so they are marked as skipped before anything else happens.
        $this->markNonexistentRowsAsSkipped->handle($eloquent, $data, $selectColumns, $deletedAtColumn, $withTrashed);

        // A row which does not exist in the database is not saved at all, so it takes part in
        // no saving/saved event and is not returned. It is decided here, before any listener
        // of this scenario, which may change `exists`. (The touches are a separate matter.)
        foreach ($data->getRows() as $row) {
            if (!$row->getModel()->exists) {
                $row->setSkipSaving(true);
            }
        }

        // Before updating, it needs to define the final list of models that need to be updated.
        // For this purpose, it uses events such as
        // 'saving', 'savingMany', 'updating', 'updatingMany',
        // 'deleting', 'deletingMany', 'restoring', 'restoringMany'.
        $this->dispatchSavingEventsStep->handle($eloquent, $data, $eventDispatcher);
        $this->dispatchUpdatingEventsStep->handle($eloquent, $data, $eventDispatcher);
        $this->dispatchDeletingAndRestoringEventsStep->handle($eloquent, $data, $eventDispatcher, $deletedAtColumn);

        // Update rows in the database.
        $this->updateRowsStep->handle($eloquent, $data, $dateFields, $deletedAtColumn);

        $hasEndListeners = $this->hasEndListeners($eventDispatcher);
        // The rows which are given back must describe what has been written as well.
        $needToKeepModels = $returned !== null || $hasEndListeners || $this->hasModelsInData($data);

        // Both the listeners and the models given by the user expect
        // `getChanges()` to return the changes made by this query.
        if ($needToKeepModels) {
            $this->syncChangesStep->handle($data);
        }

        // After updating, it needs to fire events ending with '-ed' such as
        // 'updated', 'updatedMany', 'deleted', 'deletedMany',
        // 'restored', 'restoredMany', 'saved', 'savedMany'.
        if ($hasEndListeners) {
            $this->dispatchUpdatedEventsStep->handle($eloquent, $data, $eventDispatcher);
        }

        // If the model has the property "touches", then it loads relations recursively
        // and touches each of them. WARNING: This operation may be really heavy.
        $this->touchRelationsStep->handle($data);

        // At the end, all old attributes should be replaced with new ones.
        if ($needToKeepModels) {
            $this->syncOriginalStep->handle($data);
        }

        // The same set of rows as the `saved` events get, collected without depending on them.
        if ($returned !== null) {
            foreach ($data->getRows() as $row) {
                if (!$row->isSkipSaving()) {
                    $returned->add($row);
                }
            }
        }
    }

    /**
     * Return true when at least one of the events ending with '-ed' has listeners.
     *
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     *
     * @return bool
     */
    private function hasEndListeners(BulkEventDispatcher $eventDispatcher): bool
    {
        return $eventDispatcher->hasListeners(BulkEventEnum::saved())
            || $eventDispatcher->hasListeners(BulkEventEnum::updated())
            || $eventDispatcher->hasListeners(BulkEventEnum::deleted())
            || $eventDispatcher->hasListeners(BulkEventEnum::restored());
    }

    /**
     * Return true when the user has passed models rather than plain arrays,
     * which means they keep the references and expect the models to be up-to-date.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     *
     * @return bool
     */
    private function hasModelsInData(BulkAccumulationEntity $data): bool
    {
        foreach ($data->getRows() as $row) {
            if ($row->getOriginal() instanceof Model) {
                return true;
            }
        }

        return false;
    }
}
