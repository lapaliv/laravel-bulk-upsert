<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

class SeparateIterableRowsFeature
{
    /**
     * @param int $chunkSize
     * @param array<int, BulkModel|mixed[]>|Collection<int, BulkModel>|iterable $rows
     * @param callable $callback
     * @return void
     */
    public function handle(
        int $chunkSize,
        iterable $rows,
        callable $callback,
    ): void {
        $chunk = [];

        foreach ($rows as $key => $row) {
            $chunk[$key] = $row;

            if ($chunkSize > 0 && count($chunk) % $chunkSize === 0) {
                $callback($chunk);
                $chunk = [];
            }
        }

        if (empty($chunk) === false) {
            $callback($chunk);
        }
    }
}
