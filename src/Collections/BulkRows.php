<?php

namespace Lapaliv\BulkUpsert\Collections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Traversable;

/**
 * @template TModel of Model
 * @template TOriginal of mixed
 *
 * @extends Collection<int, BulkRow<TModel, TOriginal>>
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
