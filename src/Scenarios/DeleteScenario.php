<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Collections\BulkRows;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Features\GetDeleteBuilderFeature;
use Lapaliv\BulkUpsert\Features\MarkNonexistentRowsAsSkippedFeature;

/**
 * @internal
 */
class DeleteScenario
{
    public function __construct(
        private MarkNonexistentRowsAsSkippedFeature $markNonexistentRowsAsSkipped,
        private GetDeleteBuilderFeature $getDeleteBuilderFeature,
        private BulkDriverManager $driverManager,
    ) {
        //
    }

    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        array $dateFields,
        ?string $deletedAtColumn,
        bool $force,
    ): void {
        if (empty($data->rows)) {
            return;
        }

        $this->markNonexistentRowsAsSkipped->handle($eloquent, $data, $data->uniqueBy, $deletedAtColumn, true);

        if ($eventDispatcher->hasListeners(BulkEventEnum::delete($force))) {
            $this->dispatchDeletingEvents($eloquent, $data, $eventDispatcher, $deletedAtColumn, $force);
        }

        $deletedAt = Carbon::now();
        $builder = $this->getDeleteBuilderFeature->handle(
            $eloquent,
            $data,
            $dateFields,
            $deletedAtColumn,
            $force,
            $deletedAt,
        );
        $driver = $this->driverManager->getForModel($eloquent);

        if ($builder instanceof UpdateBulkBuilder) {
            $driver->update($eloquent->getConnection(), $builder);
        } elseif ($builder instanceof DeleteBulkBuilder) {
            $driver->forceDelete($eloquent->getConnection(), $builder);
        } else {
            unset($builder, $driver);

            return;
        }

        unset($builder);

        $hasEndListeners = $eventDispatcher->hasListeners(BulkEventEnum::deleted($force));

        if ($hasEndListeners) {
            $this->dispatchDeletedEvents(
                $eloquent,
                $data,
                $eventDispatcher,
                $deletedAtColumn,
                $force,
                $deletedAt,
            );
        }
    }

    private function dispatchDeletingEvents(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
        bool $force
    ): void {
        $collection = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $row) {
            if ($row->skipDeleting) {
                continue;
            }

            if ($deletedAtColumn !== null
                && $force
                && $eventDispatcher->dispatch(BulkEventEnum::FORCE_DELETING, $row->model) === false
            ) {
                $row->skipDeleting = true;

                continue;
            }

            if ($eventDispatcher->dispatch(BulkEventEnum::DELETING, $row->model) === false) {
                $row->skipDeleting = true;

                continue;
            }

            $collection->push($row->model);
            $bulkRows->push(
                new BulkRow($row->model, $row->row, $data->uniqueBy)
            );
        }

        if ($deletedAtColumn !== null
            && $force
            && $eventDispatcher->dispatch(BulkEventEnum::FORCE_DELETING_MANY, $collection, $bulkRows) === false
        ) {
            foreach ($data->rows as $row) {
                $row->skipDeleting = true;
            }

            return;
        }

        if ($eventDispatcher->dispatch(BulkEventEnum::DELETING_MANY, $collection, $bulkRows) === false) {
            foreach ($data->rows as $row) {
                $row->skipDeleting = true;
            }
        }

        unset($collection, $bulkRows);
    }

    private function dispatchDeletedEvents(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
        bool $force,
        DateTime $deletedAt
    ): void {
        $collection = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $row) {
            if ($row->skipDeleting) {
                continue;
            }

            if ($deletedAtColumn) {
                $row->model->setAttribute($deletedAtColumn, $deletedAt);
            }

            $eventDispatcher->dispatch(BulkEventEnum::DELETED, $row->model);

            if ($deletedAtColumn !== null && $force) {
                $eventDispatcher->dispatch(BulkEventEnum::FORCE_DELETED, $row->model);
            }

            $collection->push($row->model);
            $bulkRows->push(
                new BulkRow($row->model, $row->row, $data->uniqueBy)
            );
        }

        $eventDispatcher->dispatch(BulkEventEnum::DELETED_MANY, $collection, $bulkRows);

        if ($deletedAtColumn !== null && $force) {
            $eventDispatcher->dispatch(BulkEventEnum::FORCE_DELETED_MANY, $collection, $bulkRows);
        }

        unset($collection, $bulkRows);
    }
}
