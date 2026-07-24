<?php

namespace Lapaliv\BulkUpsert\Scenarios\Create;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;

/**
 * Dispatches the 'creating' events for the rows which do not exist in the database yet.
 *
 * @internal
 */
class DispatchCreatingEventsStep
{
    /**
     * Dispatch 'creating' and 'creatingMany' events for provided $data->getRows().
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void
    {
        if (!$data->hasRows()) {
            return;
        }

        if (!$eventDispatcher->hasListeners(BulkEventEnum::creating())) {
            return;
        }

        // Firstly, find all models that have the property 'exists' set to false.
        // This step helps to avoid trying to create duplicates.
        $filter = fn(Model $model): bool => !$model->exists;

        if ($data->getModels($filter)->isEmpty()) {
            return;
        }

        // Secondly, it needs to fire the 'creating' event for each model.
        // If the event dispatcher returns false, the model should be skipped.
        $this->processEachRow($data, $eventDispatcher, $filter);

        // Thirdly, it needs to fire 'creatingMany' event for all models.
        // If the event dispatcher returns false, all the models should be skipped.
        /** @var \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $models */
        $models = $data->getModels($filter);

        if ($models->isNotEmpty()) {
            $this->processAllRows($models, $data, $eventDispatcher, $filter);
        }
    }

    /**
     * Fire the 'creating' event for each row.
     *
     * Iterating over `$data->getRows()` rather than over a filtered collection is deliberate:
     * `BulkAccumulationEntity::getModels()` re-indexes its result with `values()`, so its keys do
     * not address `$data`'s rows. Feeding them to `unsetRow()` removes an unrelated row as soon as
     * the row array has a gap in it — which `DispatchSavingEventsStep` leaves behind whenever a
     * `saving` listener cancels a row.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param \Closure(\Illuminate\Database\Eloquent\Model): bool $filter
     *
     * @return void
     */
    protected function processEachRow(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        Closure $filter,
    ): void
    {
        foreach ($data->getRows() as $key => $row) {
            if (!$filter($row->getModel())) {
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $dispatchingResult = $eventDispatcher->dispatch(BulkEventEnum::Creating, $row->getModel());

            if ($dispatchingResult === false) {
                $data->unsetRow((int) $key);
            }
        }
    }

    /**
     * Fire the 'creatingMany' event for all rows.
     *
     * @param \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param \Closure(\Illuminate\Database\Eloquent\Model): bool $filter
     *
     * @return void
     */
    protected function processAllRows(
        Collection $models,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        Closure $filter,
    ): void
    {
        /** @psalm-suppress MixedAssignment */
        $dispatchingResult = $eventDispatcher->dispatch(
            BulkEventEnum::CreatingMany,
            $models->values(),
            $data->getBulkRows($filter),
        );

        if ($dispatchingResult === false) {
            $data->setRows([]);
        }
    }
}
