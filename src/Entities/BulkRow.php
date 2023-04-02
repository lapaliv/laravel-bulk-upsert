<?php

namespace Lapaliv\BulkUpsert\Entities;

use Lapaliv\BulkUpsert\Contracts\BulkModel;

/**
 * @template TModel of BulkModel
 * @template TOriginal of mixed
 */
class BulkRow
{
    /**
     * @param BulkModel|TModel $model
     * @param mixed|TOriginal $original
     * @param string[] $unique
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function __construct(
        public BulkModel $model,
        public mixed $original,
        public array $unique,
    ) {
        //
    }
}
