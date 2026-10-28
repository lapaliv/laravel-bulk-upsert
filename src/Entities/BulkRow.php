<?php

namespace Lapaliv\BulkUpsert\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * The row which the listeners of the package events get.
 *
 * It keeps the model together with the value which the user gave and with the
 * names of the unique attributes of the query.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @template TOriginal of mixed
 *
 * @psalm-api
 */
readonly class BulkRow
{
    /**
     * Create a new row.
     *
     * @param TModel $model
     * @param TOriginal $original
     * @param string[] $unique
     *
     * @return void
     */
    public function __construct(
        private Model $model,
        private mixed $original,
        private array $unique,
    )
    {
        //
    }

    /**
     * The upserting/upserted model.
     * @return TModel
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * The original item from `iterable rows`.
     * @return TOriginal
     */
    public function getOriginal(): mixed
    {
        return $this->original;
    }

    /**
     * Unique fields which were used for upserting.
     * @return string[]
     */
    public function getUnique(): array
    {
        return $this->unique;
    }
}
