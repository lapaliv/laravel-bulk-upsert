<?php

namespace Lapaliv\BulkUpsert\Scenarios\Update;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * Remembers the changes of the updated models, so that the listeners are able to read them.
 *
 * @internal
 */
class SyncChangesStep
{
    /**
     * Remember the changes of each model that participated in the query.
     *
     * The listeners of the events ending with '-ed' are able to read them via `getChanges()`.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     *
     * @return void
     */
    public function handle(BulkAccumulationEntity $data): void
    {
        foreach ($data->getRows() as $row) {
            if ($row->isSkipSaving()) {
                continue;
            }

            $row->getModel()->syncChanges();
        }
    }
}
