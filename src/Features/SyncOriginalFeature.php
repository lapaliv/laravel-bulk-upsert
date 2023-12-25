<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * @internal
 */
class SyncOriginalFeature
{
    /**
     * Replace the modified attributes of each model with the corresponding attributes from the database.
     *
     * @param BulkAccumulationEntity $data
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
