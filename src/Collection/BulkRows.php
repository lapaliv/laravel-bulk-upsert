<?php

namespace Lapaliv\BulkUpsert\Collection;

use Illuminate\Support\Collection;
use Iterator;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Traversable;

/**
 * @template TModel of BulkModel
 * @template TOriginal of mixed
 *
 * @implements Iterator<int, BulkRow<TModel, TOriginal>>
 */
class BulkRows extends Collection
{
    /**
     * @return Traversable<int, BulkRow<TModel, TOriginal>>
     */
    public function getIterator(): Traversable
    {
        return parent::getIterator();
    }
}
