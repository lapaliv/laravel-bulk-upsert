<?php

namespace Lapaliv\BulkUpsert\Collection;

use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Traversable;

/**
 * @template TModel of BulkModel
 * @template TOriginal of mixed
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

    /**
     * @param $key
     * @param $default
     *
     * @return BulkRow<TModel, TOriginal>|null
     */
    public function get($key, $default = null)
    {
        return parent::get($key, $default);
    }

    /**
     * @return array<int, BulkRow<TModel, TOriginal>>
     */
    public function all()
    {
        return parent::all();
    }
}
