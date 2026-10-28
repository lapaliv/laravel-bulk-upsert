<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Scenarios\Create\DispatchCreatedEventsStep;
use Lapaliv\BulkUpsert\Scenarios\Create\DispatchCreatingEventsStep;
use Lapaliv\BulkUpsert\Scenarios\Create\DispatchDeletingEventsStep;
use Lapaliv\BulkUpsert\Scenarios\Create\DispatchSavingEventsStep;
use Lapaliv\BulkUpsert\Scenarios\Create\FillInWasRecentlyCreatedPropertyStep;
use Lapaliv\BulkUpsert\Scenarios\Create\InsertAndSelectStep;
use Lapaliv\BulkUpsert\Scenarios\Create\MatchSelectedModelsStep;
use Lapaliv\BulkUpsert\Scenarios\Create\SyncOriginalStep;
use Lapaliv\BulkUpsert\Scenarios\Shared\TouchRelationsStep;
use Lapaliv\BulkUpsert\Support\ReturnedModels;

/**
 * Inserts the rows into the database.
 *
 * The short algorithm contains the next steps:
 *
 * - Fire the 'saving' event for each model at the start of working;
 * - Fire the 'savingMany' event for all models after the 'saving' event;
 * - Fire the 'creating' event for each model after the 'savingMany' event;
 * - Fire the 'creatingMany' event for all models after the 'creating' event;
 * - Fire the 'deleting' event for each model which has $deletedAtColumn not null
 *   after the 'creatingMany' event;
 * - Fire the 'deletingMany' event for all models which have $deletedAtColumn not null
 *   after the 'deleting' event;
 * - Insert rows into the database;
 * - Exit the algorithm if a certain condition is met, indicating that there is no need
 *   to select rows back;
 * - Select rows back;
 * - Fire the 'created' event for each model;
 * - Fire the 'createdMany' event for all models after the 'created' event;
 * - Fire the 'deleted' event for each model which has $deletedAtColumn not null
 *   after the 'createdMany' event;
 * - Fire the 'deletedMany' event for all models which have $deletedAtColumn not null
 *   after the 'deleted' event;
 * - Fire the 'saved' event for each model after the 'deletedMany' event;
 * - Fire the 'savedMany' event for all models after the 'saved' event.
 */
readonly class CreateScenario
{
    /**
     * Create a new creating scenario instance.
     *
     * @param \Lapaliv\BulkUpsert\Scenarios\Create\DispatchSavingEventsStep $dispatchSavingEventsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Create\DispatchCreatingEventsStep $dispatchCreatingEventsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Create\DispatchDeletingEventsStep $dispatchDeletingEventsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Create\InsertAndSelectStep $insertAndSelectStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Create\MatchSelectedModelsStep $matchSelectedModelsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Create\FillInWasRecentlyCreatedPropertyStep $fillInWasRecentlyCreatedPropertyStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Create\DispatchCreatedEventsStep $dispatchCreatedEventsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Shared\TouchRelationsStep $touchRelationsStep
     * @param \Lapaliv\BulkUpsert\Scenarios\Create\SyncOriginalStep $syncOriginalStep
     */
    public function __construct(
        private DispatchSavingEventsStep $dispatchSavingEventsStep,
        private DispatchCreatingEventsStep $dispatchCreatingEventsStep,
        private DispatchDeletingEventsStep $dispatchDeletingEventsStep,
        private InsertAndSelectStep $insertAndSelectStep,
        private MatchSelectedModelsStep $matchSelectedModelsStep,
        private FillInWasRecentlyCreatedPropertyStep $fillInWasRecentlyCreatedPropertyStep,
        private DispatchCreatedEventsStep $dispatchCreatedEventsStep,
        private TouchRelationsStep $touchRelationsStep,
        private SyncOriginalStep $syncOriginalStep,
    )
    {
        //
    }

    /**
     * Run the scenario.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param bool $ignore
     * @param string[] $dateFields
     * @param string[] $selectColumns
     * @param string|null $deletedAtColumn
     * @param \Lapaliv\BulkUpsert\Support\ReturnedModels|null $returned Collects the rows to give back.
     * @param bool $readBack Whether the inserted rows have to be read back even without listeners.
     * @param bool $duplicatesOnly Whether `$ignore` may skip a duplicate key only, not other errors.
     *
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        bool $ignore,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
        ?ReturnedModels $returned = null,
        bool $readBack = false,
        bool $duplicatesOnly = false,
    ): void
    {
        if (!$data->hasRows()) {
            return;
        }

        // Before inserting, it needs to define the final list of models that need to be created.
        // For this purpose, it uses events such as
        // 'saving', 'savingMany', 'creating', 'creatingMany', 'deleting', 'deletingMany'.
        $this->dispatchSavingEventsStep->handle($data, $eventDispatcher);
        $this->dispatchCreatingEventsStep->handle($data, $eventDispatcher);
        $this->dispatchDeletingEventsStep->handle($data, $eventDispatcher, $deletedAtColumn);

        // Insert rows into the database and select them.
        // After the selection, it needs to match the selected models with the models in the memory.
        $this->insert(
            $data,
            $eventDispatcher,
            $ignore,
            $dateFields,
            $selectColumns,
            $deletedAtColumn,
            $readBack || $returned !== null,
            $duplicatesOnly,
        );

        // The rows left in the data are the ones which took part in the insert: the same set
        // the `saved` events get, collected without depending on them.
        if ($returned !== null) {
            foreach ($data->getRows() as $row) {
                $returned->add($row);
            }
        }

        // After inserting, it needs to fire events ending with '-ed' such as
        // 'created', 'deleted', 'saved' and their '-Many' counterparts.
        $this->dispatchCreatedEventsStep->handle($data, $eventDispatcher, $deletedAtColumn);

        // If the model has the property "touches", then it loads relations recursively
        // and touches each of them. WARNING: This operation may be really heavy.
        $this->touchRelationsStep->handle($data);

        // At the end, all old attributes should be replaced with new ones.
        $this->syncOriginalStep->handle($data);
    }

    /**
     * Insert rows into the database, select them,
     * and replace models in the memory with models from the database.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param bool $ignore
     * @param string[] $dateFields
     * @param string[] $selectColumns
     * @param string|null $deletedAtColumn
     * @param bool $returnModels Whether the rows have to be read back to be given back.
     * @param bool $duplicatesOnly Whether `$ignore` may skip a duplicate key only, not other errors.
     *
     * @return void
     */
    private function insert(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        bool $ignore,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
        bool $returnModels,
        bool $duplicatesOnly,
    ): void
    {
        $startedAt = Carbon::now();
        $result = $this->insertAndSelectStep->handle(
            $data,
            $eventDispatcher,
            $ignore,
            $dateFields,
            $selectColumns,
            $deletedAtColumn,
            $returnModels,
            $duplicatesOnly,
        );

        if ($result === null) {
            $data->setRows([]);

            return;
        }

        /** @var \Illuminate\Support\Collection<int|string, \Illuminate\Database\Eloquent\Model> $existingRows */
        [
            'insertResult' => $insertResult,
            'existingRows' => $existingRows,
        ] = $result;

        if ($existingRows->isEmpty()) {
            return;
        }

        $this->matchSelectedModelsStep->handle($data, $existingRows);

        // Every row may have been left unmatched (e.g. skipped by a duplicate of another index).
        if (!$data->hasRows()) {
            return;
        }

        $this->fillInWasRecentlyCreatedPropertyStep->handle($data, $insertResult, $dateFields, $startedAt);
    }
}
