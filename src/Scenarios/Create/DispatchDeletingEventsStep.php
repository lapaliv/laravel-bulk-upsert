<?php

namespace Lapaliv\BulkUpsert\Scenarios\Create;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;

/**
 * Dispatches the 'deleting' events for the rows which are going to be inserted soft deleted.
 *
 * @internal
 */
class DispatchDeletingEventsStep
{
    /**
     * Dispatch 'deleting' and 'deletingMany' events for provided $data->getRows().
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string|null $deletedAtColumn
     *
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
    ): void
    {
        if ($deletedAtColumn === null) {
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
        $filter = fn(Model $model): bool => $model->getAttribute($deletedAtColumn) !== null;
        /** @var \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $models */
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
     * @param \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string $deletedAtColumn
     *
     * @return void
     */
    private function processEachRow(
        Collection $models,
        BulkEventDispatcher $eventDispatcher,
        string $deletedAtColumn,
    ): void
    {
        foreach ($models as $key => $model) {
            /** @psalm-suppress MixedAssignment */
            $dispatchingResult = $eventDispatcher->dispatch(BulkEventEnum::Deleting, $model);

            if ($dispatchingResult === false) {
                $model->setAttribute($deletedAtColumn, null);
                $models->forget($key);
            }
        }
    }

    /**
     * Fire the 'deletingMany' event for all rows.
     *
     * @param \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string $deletedAtColumn
     * @param \Closure(\Illuminate\Database\Eloquent\Model): bool $filter
     *
     * @return void
     */
    private function processAllRows(
        Collection $models,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        string $deletedAtColumn,
        Closure $filter,
    ): void
    {
        /** @psalm-suppress MixedAssignment */
        $dispatchingResult = $eventDispatcher->dispatch(
            BulkEventEnum::DeletingMany,
            $models->values(),
            $data->getBulkRows($filter),
        );

        if ($dispatchingResult === false) {
            $models->each(
                fn(Model $model): mixed => $model->setAttribute($deletedAtColumn, null)
            );
        }
    }
}
