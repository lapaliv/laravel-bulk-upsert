<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Builder;

/**
 * The Eloquent builder which the models of the package use.
 *
 * It adds the bulk methods to the usual query builder.
 *
 * @template TCollection of \Illuminate\Database\Eloquent\Collection
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends Builder<TModel>
 */
class BulkBuilder extends Builder
{
    /**
     * @use BulkBuilderTrait<TCollection, TModel>
     */
    use BulkBuilderTrait;
}
