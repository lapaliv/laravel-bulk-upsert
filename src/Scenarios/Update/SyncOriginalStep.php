<?php

namespace Lapaliv\BulkUpsert\Scenarios\Update;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Support\ModelState;

/**
 * Makes the written values the original ones of the updated models.
 *
 * @internal
 */
class SyncOriginalStep
{
    /**
     * Declare saved exactly the values which the query has written.
     *
     * The attributes which were not written stay dirty. A listener may also change an
     * attribute after the query; that change has not been written, so the original value
     * becomes the written one and the change stays dirty. The primary key has already been
     * synced by the step which ran the query.
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

            if ($written !== []) {
                ModelState::syncWrittenOriginal($model, $written);
            }
        }
    }
}
