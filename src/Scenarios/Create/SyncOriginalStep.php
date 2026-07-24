<?php

namespace Lapaliv\BulkUpsert\Scenarios\Create;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * Marks the attributes of the created models as the ones which are stored in the database.
 *
 * @internal
 */
class SyncOriginalStep
{
    /**
     * Replace the original attributes of each model with the current ones.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     *
     * @return void
     */
    public function handle(BulkAccumulationEntity $data): void
    {
        foreach ($data->getRows() as $row) {
            $row->getModel()->syncOriginal();
        }
    }
}
