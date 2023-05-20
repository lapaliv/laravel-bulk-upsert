<?php

namespace Lapaliv\BulkUpsert\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 */
class BulkAccumulationItemEntity
{
    public function __construct(
        public mixed $row,
        public Model $model,
        public bool $skipSaving = false,
        public bool $skipCreating = false,
        public bool $skipUpdating = false,
        public bool $skipDeleting = false,
        public bool $skipRestoring = false,
        public bool $isDeleting = false,
        public bool $isRestoring = false,
    ) {
        //
    }
}
