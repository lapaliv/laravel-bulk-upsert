<?php

namespace Lapaliv\BulkUpsert\Entities;

use Illuminate\Database\Eloquent\Collection;

class DividedCollectionByExistingEntity
{
    public function __construct(
        public Collection $existing,
        public Collection $nonexistent,
    ) {
        // Nothing
    }
}
