<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

class DispatchDeletedEventsFeature
{
    /**
     * Dispatch 'deleted' and 'deletedMany' events for provided $data->getRows().
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

        if (!$eventDispatcher->hasListeners(BulkEventEnum::deleted())) {
            return;
        }

        // The event must be fired only for rows that have
        // non-empty attribute $deletedAtColumn and,
        // have either been recently created or are marked as dirty
        $filter = fn (Model $model) => $model->getAttribute($deletedAtColumn) !== null
            && ($model->wasRecentlyCreated || $model->isDirty($deletedAtColumn));
        $models = $data->getModels($filter);

        if ($models->isEmpty()) {
            return;
        }

        // Then it fires the 'deleted' and 'deleteMany' events.
        foreach ($models as $model) {
            $eventDispatcher->dispatch(BulkEventEnum::DELETED, $model);
        }

        $eventDispatcher->dispatch(
            BulkEventEnum::DELETED_MANY,
            $models,
            $data->getBulkRows($filter),
        );
    }
}
