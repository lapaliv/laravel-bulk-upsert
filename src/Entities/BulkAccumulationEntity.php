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
        public array $updateOnly = [],
        public array $updateExcept = [],
    ) {
        // BulkAccumulationEntity
    }

    public function getNotSkippedModels(string $key = null): Collection
    {
        $result = new Collection();

        foreach ($this->rows as $row) {
            if ($key === null && ($row->skipCreating || $row->skipUpdating)) {
                continue;
            }

            if ($key !== null && $row->{$key}) {
                continue;
            }

            $result->push($row->model);
        }

        return $result;
    }
}
