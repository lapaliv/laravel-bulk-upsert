<?php

namespace Lapaliv\BulkUpsert\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 * @template TOriginal of mixed
 *
 * @psalm-api
 */
class BulkRow
{
    /**
     * @param Model|TModel $model
     * @param mixed|TOriginal $original
     * @param string[] $unique
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function __construct(
        public Model $model,
        public mixed $original,
        public array $unique,
    ) {
        //
    }
}
