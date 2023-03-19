<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Query\Builder;

trait BulkModelTrait
{
    public function newEloquentBuilder(Builder $builder): BulkBuilder
    {
        return new BulkBuilder($builder);
    }
}
