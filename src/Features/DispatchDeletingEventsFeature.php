<?php

namespace Lapaliv\BulkUpsert\Features;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

/**
 * @internal
 */
class DispatchDeletingEventsFeature
{
    /**
     * Dispatch 'deleting' and 'deletingMany' events for provided $data->getRows().
     *
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     * @param string|null $deletedAtColumn
     *
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
    ): void {
        if (!$deletedAtColumn) {
            return;
        }

        if (!$data->hasRows()) {
            return;
        }

        if (!$eventDispatcher->hasListeners(BulkEventEnum::delete())) {
            return;
        }

        // Firstly, find all models that have the attribute $deletedAtColumn set to not null.
        // For other models, events should not be triggered.
        $filter = fn (Model $model) => $model->getAttribute($deletedAtColumn) !== null;
        $models = $data->getModels($filter);

        if ($models->isEmpty()) {
            return;
        }

        // Secondly, it needs to fire "deleting" event for each model.
        // If the event dispatcher returns false, it should be skipped.
        $this->processEachRow($models, $eventDispatcher, $deletedAtColumn);

        // Thirdly, it needs to fire "deletingMany" event for all models.
        // If the event dispatcher returns false, it should be skipped.
        if ($models->isNotEmpty()) {
            $this->processAllRows($models, $data, $eventDispatcher, $deletedAtColumn, $filter);
        }
    }

    /**
     * Fire the 'deleting' event for each row.
     *
     * @param Collection $models
     * @param BulkEventDispatcher $eventDispatcher
     * @param string|null $deletedAtColumn
     *
     * @return void
     */
    private function processEachRow(
        Collection $models,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
    ): void {
        foreach ($models as $key => $model) {
            $dispatchingResult = $eventDispatcher->dispatch(BulkEventEnum::DELETING, $model);

            if ($dispatchingResult === false) {
                $model->setAttribute($deletedAtColumn, null);
                $models->forget($key);
            }
        }
    }

    /**
     * Fire the 'deletingMany' event for all rows.
     *
     * @param Collection $models
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     * @param string|null $deletedAtColumn
     * @param Closure $filter
     *
     * @return void
     */
    private function processAllRows(
        Collection $models,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
        Closure $filter,
    ): void {
        $dispatchingResult = $eventDispatcher->dispatch(
            BulkEventEnum::DELETING_MANY,
            $models->values(),
            $data->getBulkRows($filter),
        );

        if ($dispatchingResult === false) {
            $models->each(
                fn (Model $model) => $model->setAttribute($deletedAtColumn, null)
            );
        }
    }
}
