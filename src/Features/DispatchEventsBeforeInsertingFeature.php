<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

class DispatchEventsBeforeInsertingFeature
{
    public function __construct(
        private DispatchSavingEventsFeature $dispatchSavingEventsFeature,
        private DispatchCreatingEventsFeature $dispatchCreatingEventsFeature,
        private DispatchDeletingEventsFeature $dispatchDeletingEventsFeature,
    ) {
        //
    }

    /**
     * Dispatch all the events ending with '-ing' such as
     * 'saving', 'savingMany', 'creating', 'creatingMany', 'deleting', 'deletingMany'.
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
        $this->dispatchSavingEventsFeature->handle($data, $eventDispatcher);
        $this->dispatchCreatingEventsFeature->handle($data, $eventDispatcher);
        $this->dispatchDeletingEventsFeature->handle($data, $eventDispatcher, $deletedAtColumn);
    }
}
