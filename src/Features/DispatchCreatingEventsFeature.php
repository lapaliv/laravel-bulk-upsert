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
class DispatchCreatingEventsFeature
{
    /**
     * Dispatch 'creating' and 'creatingMany' events for provided $data->getRows().
     *
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        if (!$data->hasRows()) {
            return;
        }

        if (!$eventDispatcher->hasListeners(BulkEventEnum::creating())) {
            return;
        }

        // Firstly, find all models that have the property 'exists' set to false.
        // This step helps to avoid trying to create duplicates.
        $filter = fn (Model $model) => !$model->exists;
        $models = $data->getModels($filter);

        if ($models->isEmpty()) {
            return;
        }

        // Secondly, it needs to fire the 'creating' event for each model.
        // If the event dispatcher returns false, the model should be skipped.
        $this->processEachRow($models, $data, $eventDispatcher);

        // Thirdly, it needs to fire 'creatingMany' event for all models.
        // If the event dispatcher returns false, all the models should be skipped.
        if ($models->isNotEmpty()) {
            $this->processAllRows($models, $data, $eventDispatcher, $filter);
        }
    }

    /**
     * Fire the 'creating' event for each row.
     *
     * @param Collection $models
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    protected function processEachRow(
        Collection $models,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        foreach ($models as $key => $model) {
            $dispatchingResult = $eventDispatcher->dispatch(BulkEventEnum::CREATING, $model);

            if ($dispatchingResult === false) {
                $data->unsetRow($key);
                $models->forget($key);
            }
        }
    }

    /**
     * Fire the 'creatingMany' event for all rows.
     *
     * @param Collection $models
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     * @param Closure $filter
     *
     * @return void
     */
    protected function processAllRows(
        Collection $models,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        Closure $filter,
    ): void {
        $dispatchingResult = $eventDispatcher->dispatch(
            BulkEventEnum::CREATING_MANY,
            $models->values(),
            $data->getBulkRows($filter),
        );

        if ($dispatchingResult === false) {
            $data->setRows([]);
        }
    }
}
