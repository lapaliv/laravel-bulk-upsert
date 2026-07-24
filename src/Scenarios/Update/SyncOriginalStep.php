<?php

namespace Lapaliv\BulkUpsert\Scenarios\Update;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * Marks the attributes of the updated models as the ones which are stored in the database.
 *
 * @internal
 */
class SyncOriginalStep
{
    /**
     * Replace the original attributes of each model that participated in the query.
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

            $row->getModel()->syncOriginal();
        }
    }
}
