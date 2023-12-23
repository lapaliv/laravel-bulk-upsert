<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

class DispatchEventsAfterInsertingFeature
{
    public function __construct(
        private DispatchCreatedEventsFeature $dispatchCreatedEventsFeature,
        private DispatchDeletedEventsFeature $dispatchDeletedEventsFeature,
        private DispatchSavedEventsFeature $dispatchSavedEventsFeature,
    ) {
        //
    }

    /**
     * Dispatch all the events ending with '-ed' such as
     * 'created', 'createdMany', 'deleted', 'deletedMany', 'saving', 'savedMany'.
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
        $this->dispatchCreatedEventsFeature->handle($data, $eventDispatcher);
        $this->dispatchDeletedEventsFeature->handle($data, $eventDispatcher, $deletedAtColumn);
        $this->dispatchSavedEventsFeature->handle($data, $eventDispatcher);
    }
}
