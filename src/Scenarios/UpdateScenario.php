<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Lapaliv\BulkUpsert\Collection\BulkRows;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\DriverManager;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Features\FinishSaveFeature;
use Lapaliv\BulkUpsert\Features\GetUpdateBuilderFeature;
use Lapaliv\BulkUpsert\Features\MarkNonexistentRowsAsSkippedFeature;

class UpdateScenario
{
    public function __construct(
        private MarkNonexistentRowsAsSkippedFeature $markNonexistentRowsAsSkipped,
        private GetUpdateBuilderFeature $getUpdateBuilderFeature,
        private DriverManager $driverManager,
        private FinishSaveFeature $finishSaveFeature,
    ) {
        //
    }

    public function handle(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
    ): void {
        if (empty($data->rows)) {
            return;
        }

        $this->markNonexistentRowsAsSkipped->handle($eloquent, $data, $selectColumns, $deletedAtColumn);

        if ($eventDispatcher->hasListeners(BulkEventEnum::updating())) {
            $this->dispatchSavingEvents($eloquent, $data, $eventDispatcher);
            $this->dispatchUpdatingEvents($eloquent, $data, $eventDispatcher);
            $this->dispatchDeletingAndRestoringEvents($eloquent, $data, $eventDispatcher, $deletedAtColumn);
        }

        if ($data->hasNotSkippedModels('skipUpdating') === false) {
            return;
        }

        $this->freshTimestamps($eloquent, $data);

        $builder = $this->getUpdateBuilderFeature->handle($eloquent, $data, $dateFields, $deletedAtColumn);
        $driver = $this->driverManager->getForModel($eloquent);

        if ($builder !== null) {
            $driver->update($eloquent->getConnection(), $builder);
            unset($builder);
        }

        if ($eventDispatcher->hasListeners(BulkEventEnum::updated()) === false) {
            unset($driver);

            return;
        }

        $this->fireUpdatedEvents($eloquent, $data, $eventDispatcher);
        $this->finishSaveFeature->handle($eloquent, $data, $eventDispatcher, $eloquent->getConnection(), $driver);
    }

    private function dispatchSavingEvents(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        $models = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $row) {
            if ($eventDispatcher->dispatch(BulkEventEnum::SAVING, $row->model) === false) {
                $row->skipSaving = true;

                continue;
            }

            $models->push($row->model);
            $bulkRows->push(
                new BulkRow($row->model, $row->row, $data->uniqueBy)
            );
        }

        if ($models->isNotEmpty()) {
            $eventResult = $eventDispatcher->dispatch(BulkEventEnum::SAVING_MANY, $models, $bulkRows);

            if ($eventResult === false) {
                foreach ($data->rows as $row) {
                    $row->skipSaving = true;
                }
            }
        }

        unset($models, $bulkRows);
    }

    private function dispatchUpdatingEvents(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        $models = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $row) {
            if ($row->skipSaving || $row->skipUpdating) {
                continue;
            }

            if ($eventDispatcher->dispatch(BulkEventEnum::UPDATING, $row->model) === false) {
                $row->skipUpdating = true;

                continue;
            }

            $models->push($row->model);
            $bulkRows->push(
                new BulkRow($row->model, $row->row, $data->uniqueBy)
            );
        }

        if ($models->isNotEmpty()) {
            $eventResult = $eventDispatcher->dispatch(BulkEventEnum::UPDATING_MANY, $models, $bulkRows);

            if ($eventResult === false) {
                foreach ($data->rows as $row) {
                    $row->skipUpdating = true;
                }
            }
        }

        unset($models, $bulkRows);
    }

    private function dispatchDeletingAndRestoringEvents(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        string $deletedAtColumn,
    ): void {
        $deletingModels = $eloquent->newCollection();
        $restoringModels = $eloquent->newCollection();
        $deletingBulkRows = new BulkRows();
        $restoringBulkRows = new BulkRows();

        foreach ($data->rows as $row) {
            if ($row->skipSaving) {
                continue;
            }

            if ($row->skipDeleting === false
                && $row->model->getAttribute($deletedAtColumn) !== null
                && $row->model->getOriginal($deletedAtColumn) === null
            ) {
                $row->isDeleting = true;

                if ($eventDispatcher->dispatch(BulkEventEnum::DELETING, $row->model) === false) {
                    $row->skipDeleting = true;
                } else {
                    $deletingModels->push($row->model);
                    $deletingBulkRows->push(
                        new BulkRow($row->model, $row->row, $data->uniqueBy)
                    );
                }
            }

            if ($row->skipRestoring === false
                && $row->model->getAttribute($deletedAtColumn) === null
                && $row->model->getOriginal($deletedAtColumn) !== null
            ) {
                $row->isRestoring = true;

                if ($eventDispatcher->dispatch(BulkEventEnum::RESTORING, $row->model) === false) {
                    $row->skipRestoring = true;
                } else {
                    $restoringModels->push($row->model);
                    $restoringBulkRows->push(
                        new BulkRow($row->model, $row->row, $data->uniqueBy)
                    );
                }
            }
        }

        if ($deletingModels->isNotEmpty()) {
            $eventResult = $eventDispatcher->dispatch(BulkEventEnum::DELETING_MANY, $deletingModels, $deletingBulkRows);

            if ($eventResult === false) {
                foreach ($data->rows as $row) {
                    if ($row->isDeleting) {
                        $row->skipDeleting = true;
                    }
                }
            }
        }

        if ($restoringModels->isNotEmpty()) {
            $eventResult = $eventDispatcher->dispatch(BulkEventEnum::RESTORING_MANY, $restoringModels, $restoringBulkRows);

            if ($eventResult === false) {
                foreach ($data->rows as $row) {
                    if ($row->isRestoring) {
                        $row->skipRestoring = true;
                    }
                }
            }
        }

        unset($deletingModels, $deletingBulkRows, $restoringModels, $restoringBulkRows, $eventResult);
    }

    private function freshTimestamps(BulkModel $eloquent, BulkAccumulationEntity $data): void
    {
        if ($eloquent->usesTimestamps()) {
            foreach ($data->rows as $accumulatedRow) {
                if ($accumulatedRow->skipSaving || $accumulatedRow->skipUpdating) {
                    continue;
                }

                $accumulatedRow->model->updateTimestamps();
            }
        }
    }

    private function fireUpdatedEvents(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        $savedModels = $eloquent->newCollection();
        $updatedModels = $eloquent->newCollection();
        $deletedModels = $eloquent->newCollection();
        $restoredModels = $eloquent->newCollection();
        $savedBulkRows = new BulkRows();
        $updatedBulkRows = new BulkRows();
        $deletedBulkRows = new BulkRows();
        $restoredBulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($accumulatedRow->skipSaving) {
                continue;
            }

            if ($accumulatedRow->skipUpdating === false) {
                $eventDispatcher->dispatch(BulkEventEnum::UPDATED, $accumulatedRow->model);
                $updatedModels->push($accumulatedRow->model);
                $updatedBulkRows->push(
                    new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                );
            }

            if ($accumulatedRow->isDeleting && $accumulatedRow->skipDeleting === false) {
                $eventDispatcher->dispatch(BulkEventEnum::DELETED, $accumulatedRow->model);
                $deletedModels->push($accumulatedRow->model);
                $deletedBulkRows->push(
                    new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                );
            }

            if ($accumulatedRow->isRestoring && $accumulatedRow->skipRestoring === false) {
                $eventDispatcher->dispatch(BulkEventEnum::RESTORED, $accumulatedRow->model);
                $restoredModels->push($accumulatedRow->model);
                $restoredBulkRows->push(
                    new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                );
            }

            if ($accumulatedRow->skipSaving === false) {
                $eventDispatcher->dispatch(BulkEventEnum::SAVED, $accumulatedRow->model);
                $savedModels->push($accumulatedRow->model);
                $savedBulkRows->push(
                    new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                );
            }
        }

        if ($updatedModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::UPDATED_MANY, $updatedModels, $updatedBulkRows);
        }

        if ($deletedModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::DELETED_MANY, $deletedModels, $deletedBulkRows);
        }

        if ($restoredModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::RESTORED_MANY, $restoredModels, $restoredBulkRows);
        }

        if ($savedModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::SAVED_MANY, $savedModels, $savedBulkRows);
        }

        unset(
            $savedBulkRows, $updatedBulkRows, $deletedBulkRows, $restoredBulkRows,
            $savedModels, $updatedModels, $deletedModels, $restoredModels,
        );
    }
}
