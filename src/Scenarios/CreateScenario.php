<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Collections\BulkRows;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Features\GetInsertBuilderFeature;
use Lapaliv\BulkUpsert\Features\GetUniqueKeyFeature;
use Lapaliv\BulkUpsert\Features\SelectExistingRowsFeature;
use Lapaliv\BulkUpsert\Features\TouchRelationsFeature;

/**
 * @internal
 */
class CreateScenario
{
    public function __construct(
        private GetInsertBuilderFeature $getInsertBuilderFeature,
        private BulkDriverManager $driverManager,
        private SelectExistingRowsFeature $selectExistingRowsFeature,
        private GetUniqueKeyFeature $getUniqueKeyFeature,
        private TouchRelationsFeature $touchRelationsFeature,
    ) {
        //
    }

    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        bool $ignore,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
    ): void {
        if (empty($data->rows)) {
            return;
        }

        if ($eventDispatcher->hasListeners(BulkEventEnum::saving())
            || $eventDispatcher->hasListeners(BulkEventEnum::creating())
            || $eventDispatcher->hasListeners(BulkEventEnum::delete())
        ) {
            $this->dispatchSavingEvents($eloquent, $data, $eventDispatcher);
            $this->dispatchCreatingEvents($eloquent, $data, $eventDispatcher);

            if ($deletedAtColumn !== null) {
                $this->dispatchDeletingEvents($eloquent, $data, $eventDispatcher, $deletedAtColumn);
            }
        }

        $startedAt = new DateTime();
        $builder = $this->getInsertBuilderFeature->handle(
            $eloquent,
            $data,
            $ignore,
            $dateFields,
            $selectColumns,
            $deletedAtColumn,
        );

        if ($builder === null) {
            unset($builder, $startedAt);

            return;
        }

        $driver = $this->driverManager->getForModel($eloquent);
        $hasTouchedRelations = !empty($eloquent->getTouchedRelations());
        $needToSelect = $hasTouchedRelations
            || $eventDispatcher->hasListeners(BulkEventEnum::saved())
            || $eventDispatcher->hasListeners(BulkEventEnum::created())
            || $eventDispatcher->hasListeners(BulkEventEnum::deleted());

        if (!$needToSelect) {
            $driver->quietInsert($eloquent->getConnection(), $builder);
            unset($builder, $driver, $startedAt, $hasTouchedRelations, $needToSelect);

            return;
        }

        $insertResult = $driver->insertWithResult(
            $eloquent->getConnection(),
            $builder,
            $eloquent->getIncrementing() ? $eloquent->getKeyName() : null
        );
        unset($builder);
        $insertedRows = $insertResult->getRows();

        if (is_array($insertedRows)) {
            $models = $eloquent->newCollection();

            foreach ($insertedRows as $row) {
                $model = new $eloquent();
                $model->exists = true;
                $model->wasRecentlyCreated = true;
                $model->setRawAttributes((array) $row);
                $models->push($model);
            }
        } else {
            $models = $this->selectExistingRowsFeature->handle(
                $eloquent,
                $data->getNotSkippedModels('skipCreating'),
                $data->uniqueBy,
                $selectColumns,
                $deletedAtColumn
            );
        }

        $this->prepareModelsForGiving($eloquent, $data, $models, $insertResult, $startedAt);
        unset($models, $insertedRows);

        $this->fireCreatedEvents($eloquent, $data, $eventDispatcher);

        unset($startedAt);

        if ($hasTouchedRelations) {
            $this->touchRelationsFeature->handle($eloquent, $data, $eventDispatcher, $eloquent->getConnection(), $driver);
        }

        unset($driver);

        $this->syncOriginal($data);
    }

    private function dispatchSavingEvents(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        if (!$eventDispatcher->hasListeners(BulkEventEnum::saving())) {
            return;
        }

        $models = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($eventDispatcher->dispatch(BulkEventEnum::SAVING, $accumulatedRow->model) === false) {
                $accumulatedRow->skipSaving = true;

                continue;
            }

            $models->push($accumulatedRow->model);
            $bulkRows->push(
                new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
            );
        }

        if ($models->isNotEmpty()
            && $eventDispatcher->dispatch(BulkEventEnum::SAVING_MANY, $models, $bulkRows) === false
        ) {
            foreach ($data->rows as $row) {
                $row->skipSaving = true;
            }
        }

        unset($models, $bulkRows);
    }

    private function dispatchCreatingEvents(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        if (!$eventDispatcher->hasListeners(BulkEventEnum::creating())) {
            return;
        }

        $models = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($accumulatedRow->skipSaving) {
                continue;
            }

            if ($accumulatedRow->model->exists) {
                $accumulatedRow->skipCreating = true;

                continue;
            }

            if ($eventDispatcher->dispatch(BulkEventEnum::CREATING, $accumulatedRow->model) === false) {
                $accumulatedRow->skipCreating = true;

                continue;
            }

            $models->push($accumulatedRow->model);
            $bulkRows->push(
                new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
            );
        }

        if ($models->isNotEmpty()
            && $eventDispatcher->dispatch(BulkEventEnum::CREATING_MANY, $models, $bulkRows) === false
        ) {
            foreach ($data->rows as $row) {
                $row->skipCreating = true;
            }
        }

        unset($models, $bulkRows);
    }

    private function dispatchDeletingEvents(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        string $deletedAtColumn
    ): void {
        if (!$eventDispatcher->hasListeners(BulkEventEnum::delete())) {
            return;
        }

        $models = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($accumulatedRow->skipSaving || $accumulatedRow->skipCreating) {
                continue;
            }

            if ($accumulatedRow->model->getAttribute($deletedAtColumn) === null) {
                continue;
            }

            $accumulatedRow->isDeleting = true;

            if ($eventDispatcher->dispatch(BulkEventEnum::DELETING, $accumulatedRow->model) === false) {
                $accumulatedRow->skipDeleting = true;

                continue;
            }

            $models->push($accumulatedRow->model);
            $bulkRows->push(
                new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
            );
        }

        if ($models->isNotEmpty()
            && $eventDispatcher->dispatch(BulkEventEnum::DELETING_MANY, $models, $bulkRows) === false
        ) {
            foreach ($data->rows as $row) {
                if ($row->isDeleting) {
                    $row->skipDeleting = true;
                }
            }
        }

        unset($models, $bulkRows);
    }

    private function prepareModelsForGiving(
        Model $eloquent,
        BulkAccumulationEntity $data,
        Collection $existingRows,
        BulkInsertResult $insertResult,
        DateTimeInterface $startedAt,
    ): void {
        $hasIncrementing = $eloquent->getIncrementing();
        $createdAtColumn = $eloquent->usesTimestamps()
            ? $eloquent->getCreatedAtColumn()
            : null;
        $keyedExistingRows = $existingRows->keyBy(
            function (Model $model) use ($data): string {
                return $this->getUniqueKeyFeature->handle($model, $data->uniqueBy);
            }
        );
        $maxIdBeforeInserting = $insertResult->getMaxPrimaryBeforeInserting();

        foreach ($data->rows as $row) {
            if ($row->skipSaving || $row->skipCreating) {
                continue;
            }

            $key = $this->getUniqueKeyFeature->handle($row->model, $data->uniqueBy);

            if ($keyedExistingRows->has($key)) {
                /** @var Model $existingRow */
                $existingRow = $keyedExistingRows->get($key);
                $row->model = $existingRow;

                if ($row->model->wasRecentlyCreated) {
                    continue;
                }

                if ($hasIncrementing && $maxIdBeforeInserting !== null) {
                    $row->model->wasRecentlyCreated = $row->model->getKey() > $maxIdBeforeInserting;
                } elseif ($createdAtColumn !== null
                    && $row->model->getAttribute($createdAtColumn) instanceof DateTimeInterface
                ) {
                    $row->model->wasRecentlyCreated = $startedAt < $existingRow->getAttribute($createdAtColumn);
                }
            }
        }
    }

    private function fireCreatedEvents(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        $hasSavedListeners = $eventDispatcher->hasListeners(BulkEventEnum::saved());
        $hasCreatedListeners = $eventDispatcher->hasListeners(BulkEventEnum::created());
        $hasDeletedListeners = $eventDispatcher->hasListeners(BulkEventEnum::deleted());

        $savedModels = $eloquent->newCollection();
        $createdModels = $eloquent->newCollection();
        $deletedModels = $eloquent->newCollection();
        $savedBulkRows = new BulkRows();
        $createdBulkRows = new BulkRows();
        $deletedBulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($accumulatedRow->skipSaving) {
                continue;
            }

            if (!$accumulatedRow->skipCreating && $accumulatedRow->model->exists) {
                if ($hasCreatedListeners) {
                    $createdModels->push($accumulatedRow->model);
                    $createdBulkRows->push(
                        new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                    );
                    $eventDispatcher->dispatch(BulkEventEnum::CREATED, $accumulatedRow->model);
                }

                if ($hasDeletedListeners && $accumulatedRow->isDeleting && !$accumulatedRow->skipDeleting) {
                    $deletedModels->push($accumulatedRow->model);
                    $deletedBulkRows->push(
                        new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                    );
                    $eventDispatcher->dispatch(BulkEventEnum::DELETED, $accumulatedRow->model);
                }
            }

            if ($hasSavedListeners) {
                $savedModels->push($accumulatedRow->model);
                $savedBulkRows->push(
                    new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                );
                $eventDispatcher->dispatch(BulkEventEnum::SAVED, $accumulatedRow->model);
            }
        }

        if ($createdModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::CREATED_MANY, $createdModels, $createdBulkRows);
        }

        unset($createdModels, $createdBulkRows);

        if ($deletedModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::DELETED_MANY, $deletedModels, $deletedBulkRows);
        }

        unset($deletedBulkRows, $deletedModels);

        if ($savedModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::SAVED_MANY, $savedModels, $savedBulkRows);
        }

        unset($savedBulkRows, $savedModels);
    }

    private function syncOriginal(BulkAccumulationEntity $data): void
    {
        foreach ($data->rows as $row) {
            if ($row->skipSaving) {
                continue;
            }

            $row->model->syncOriginal();
        }
    }
}
