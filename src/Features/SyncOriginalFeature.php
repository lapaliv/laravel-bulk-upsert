<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

class SyncOriginalFeature
{
    public function handle(BulkAccumulationEntity $data): void
    {
        foreach ($data->getRows() as $row) {
            $row->getModel()->syncOriginal();
        }
    }
}
