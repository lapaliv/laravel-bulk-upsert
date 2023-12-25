<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

/**
 * @internal
 */
class DispatchCreatedEventsFeature
{
    /**
     * Dispatch the 'created' and 'createdMany' events for provided $data->getRows().
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

        if (!$eventDispatcher->hasListeners(BulkEventEnum::created())) {
            return;
        }

        // The event must be fired only for models
        // that have the property $wasRecentlyCreated set to true.
        // If the model had been created before, then it doesn't need to fire this event.
        $filter = fn (Model $model) => $model->wasRecentlyCreated;
        $models = $data->getModels($filter);

        if ($models->isEmpty()) {
            return;
        }

        foreach ($models as $model) {
            $eventDispatcher->dispatch(BulkEventEnum::CREATED, $model);
        }

        $eventDispatcher->dispatch(
            BulkEventEnum::CREATED_MANY,
            $models,
            $data->getBulkRows($filter),
        );
    }
}
