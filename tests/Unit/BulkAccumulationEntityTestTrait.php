<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;

trait BulkAccumulationEntityTestTrait
{
    protected function getBulkAccumulationEntityFromCollection(Collection $collection, array $uniqueBy = ['id']): BulkAccumulationEntity
    {
        $rows = [];

        foreach ($collection as $item) {
            $rows[] = new BulkAccumulationItemEntity(
                original: $item,
                model: $item,
            );
        }

        return new BulkAccumulationEntity(
            rows: $rows,
            uniqueBy: $uniqueBy,
        );
    }
}
