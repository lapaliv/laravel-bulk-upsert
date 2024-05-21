<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TCollection of Collection
 * @template TModel of Model
 */
class BulkBuilder extends Builder
{
    /**
     * @use BulkBuilderTrait<TCollection, TModel>
     */
    use BulkBuilderTrait;
}
