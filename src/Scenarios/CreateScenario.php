<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Features\DispatchEventsAfterInsertingFeature;
use Lapaliv\BulkUpsert\Features\DispatchEventsBeforeInsertingFeature;
use Lapaliv\BulkUpsert\Features\FillInWasRecentlyCreatedPropertyFeature;
use Lapaliv\BulkUpsert\Features\InsertAndSelectFeature;
use Lapaliv\BulkUpsert\Features\MatchSelectedModelsFeature;
use Lapaliv\BulkUpsert\Features\SyncOriginalFeature;
use Lapaliv\BulkUpsert\Features\TouchRelationsFeature;

class CreateScenario
{
    /**
     * The scenario inserts rows into the database. The short algorithm is
     * 1. Fire the 'saving' event for each model at the start of working;
     * 2. Fire the 'savingMany' event for all models after the 'saving' event;
     * 3. Fire the 'creating' event for each model after the 'savingMany' event;
     * 4. Fire the 'creatingMany' event for all models after the 'creating' event;
     * 5. Fire the 'deleting' event for each model which has $deletedAtColumn not null after the 'creatingMany' event;
     * 6. Fire the 'deletingMany' event for all models which have $deletedAtColumn not null after the 'deleting' event;
     * 7. Insert rows into the database;
     * 8. Exit the algorithm if a certain condition is met, indicating that there is no need to select rows back;
     * 9. Select rows back;
     * 10. Fire the 'created' event for each model;
     * 11. Fire the 'createdMany' event for all models after the 'created' event;
     * 12. Fire the 'deleted' event for each model which has $deletedAtColumn not null after the 'createdMany' event;
     * 13. Fire the 'deletedMany' event for all models which have $deletedAtColumn not null after the 'deleted' event;
     * 14. Fire the 'saved' event for each model after the 'deletedMany' event;
     * 15. Fire the 'savedMany' event for all models after the 'saved' event.
     *
     * @param DispatchEventsBeforeInsertingFeature $dispatchEventsBeforeInsertingFeature
     * @param InsertAndSelectFeature $insertAndSelectFeature
     * @param MatchSelectedModelsFeature $matchSelectedModelsFeature
     * @param FillInWasRecentlyCreatedPropertyFeature $fillInWasRecentlyCreatedPropertyFeature
     * @param DispatchEventsAfterInsertingFeature $dispatchEventsAfterInsertingFeature
     * @param TouchRelationsFeature $touchRelationsFeature
     * @param SyncOriginalFeature $syncOriginalFeature
     */
    public function __construct(
        private DispatchEventsBeforeInsertingFeature $dispatchEventsBeforeInsertingFeature,
        private InsertAndSelectFeature $insertAndSelectFeature,
        private MatchSelectedModelsFeature $matchSelectedModelsFeature,
        private FillInWasRecentlyCreatedPropertyFeature $fillInWasRecentlyCreatedPropertyFeature,
        private DispatchEventsAfterInsertingFeature $dispatchEventsAfterInsertingFeature,
        private TouchRelationsFeature $touchRelationsFeature,
        private SyncOriginalFeature $syncOriginalFeature,
    ) {
        //
    }

    /**
     * Run the scenario.
     *
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     * @param bool $ignore
     * @param string[] $dateFields
     * @param string[] $selectColumns
     * @param string|null $deletedAtColumn
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
    ): void {
        if (!$data->hasRows()) {
            return;
        }

        // Before inserting, it needs to define the final list of models that need to be created.
        // For this purpose, it uses events such as
        // 'saving', 'savingMany', 'creating', 'creatingMany', 'deleting', 'deletingMany'.
        $this->dispatchEventsBeforeInsertingFeature
            ->handle($data, $eventDispatcher, $deletedAtColumn);

        // Insert rows into the database and select them.
        // After the selection, it needs to match the selected models with the models in the memory.
        $this->insert(
            $data,
            $eventDispatcher,
            $ignore,
            $dateFields,
            $selectColumns,
            $deletedAtColumn,
        );

        // After inserting, it needs to fire events ending with '-ed' such as
        // 'created', 'createdMany', 'deleted', 'deletedMany', 'saved', 'savedMany'.
        $this->dispatchEventsAfterInsertingFeature
            ->handle($data, $eventDispatcher, $deletedAtColumn);

        // If the model has the property "touches", then it loads relations recursively
        // and touches each of them. WARNING: This operation may be really heavy.
        $this->touchRelationsFeature->handle($data);

        // At the end, all old attributes should be replaced with new ones.
        $this->syncOriginalFeature->handle($data);
    }

    /**
     * Insert rows into the database, select them,
     * and replace models in the memory with models from the database.
     *
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     * @param bool $ignore
     * @param string[] $dateFields
     * @param string[] $selectColumns
     * @param string|null $deletedAtColumn
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
    ): void {
        $startedAt = Carbon::now();
        $result = $this->insertAndSelectFeature->handle($data, $eventDispatcher, $ignore, $dateFields, $selectColumns, $deletedAtColumn);

        if ($result === null) {
            $data->setRows([]);

            return;
        }

        [
            'insertResult' => $insertResult,
            'existingRows' => $existingRows,
        ] = $result;

        if ($existingRows->isEmpty()) {
            return;
        }

        $this->matchSelectedModelsFeature->handle($data, $existingRows);
        $this->fillInWasRecentlyCreatedPropertyFeature->handle($data, $insertResult, $dateFields, $startedAt);
    }
}
