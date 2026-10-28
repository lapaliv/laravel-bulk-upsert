<?php

namespace Lapaliv\BulkUpsert\Collections;

use Illuminate\Support\Collection;

/**
 * The collection of the rows which the listeners of the package events get.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @template TOriginal of mixed
 *
 * @extends Collection<int, \Lapaliv\BulkUpsert\Entities\BulkRow<TModel, TOriginal>>
 */
class BulkRowCollection extends Collection
{
    //
}
