<?php

namespace Lapaliv\BulkUpsert\Entities;

use Lapaliv\BulkUpsert\Contracts\BulkModel;

/**
 * @internal
 */
class BulkAccumulationItemEntity
{
    public function __construct(
        public mixed $row,
        public BulkModel $model,
        public bool $skipped = false,
    ) {
        //
    }
}
