<?php

namespace Lapaliv\BulkUpsert\Scenarios\Update;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Support\ModelState;

/**
 * Remembers the changes of the updated models, so that the listeners are able to read them.
 *
 * @internal
 */
class SyncChangesStep
{
    /**
     * Remember the changes which the query has written for each model.
     *
     * The listeners of the events ending with '-ed' are able to read them via `getChanges()`.
     * Only the written attributes become changes: the ones which were cancelled, dropped by
     * `updateOnly()`/`updateAllExcept()` or never written stay unsaved. The primary key is
     * never written, so it is never a change either.
     *
     * A row which took part but wrote nothing gets no changes, so the listeners never read
     * the changes of a previous write. A cancelled row keeps its state untouched.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     *
     * @return void
     */
    public function handle(BulkAccumulationEntity $data): void
    {
        foreach ($data->getRows() as $row) {
            $written = $row->getWrittenUpdate();

            if ($written === null) {
                continue;
            }

            $model = $row->getModel();
            unset($written[$model->getKeyName()]);

            ModelState::syncWrittenChanges($model, $written);
        }
    }
}
