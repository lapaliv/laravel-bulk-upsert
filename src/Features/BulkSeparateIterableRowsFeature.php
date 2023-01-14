<?php

namespace Lapaliv\BulkUpsert\Features;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

class BulkSeparateIterableRowsFeature
{
    /**
     * @param int $chunkSize
     * @param array<int, BulkModel|mixed[]>|Collection<int, BulkModel>|iterable $rows
     * @param Closure $callback
     * @return void
     */
    public function handle(
        int $chunkSize,
        iterable $rows,
        Closure $callback,
    ): void
    {
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
