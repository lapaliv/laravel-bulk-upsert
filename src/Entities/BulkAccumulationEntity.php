<?php

namespace Lapaliv\BulkUpsert\Entities;

use Illuminate\Database\Eloquent\Collection;

/**
 * @internal
 */
class BulkAccumulationEntity
{
    /**
     * @param string[] $uniqueBy
     * @param BulkAccumulationItemEntity[] $rows
     */
    public function __construct(
        public array $uniqueBy,
        public array $rows = [],
    ) {
        //
    }

    public function getModels(): Collection
    {
        $result = new Collection();

        foreach ($this->rows as $row) {
            if ($row->skipped) {
                continue;
            }

            $result->push($row->model);
        }

        return $result;
    }
}
