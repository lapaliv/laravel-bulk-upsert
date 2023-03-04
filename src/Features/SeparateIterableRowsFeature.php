<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

namespace Lapaliv\BulkUpsert\Features;

use Generator;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

class SeparateIterableRowsFeature
{
    /**
     * @param int $chunkSize
     * @param array<int, BulkModel|mixed[]>|Collection|iterable $rows
     * @return Generator
     */
    public function handle(int $chunkSize, iterable $rows): Generator
    {
        $chunk = [];

        foreach ($rows as $key => $row) {
            $chunk[$key] = $row;

            if ($chunkSize > 0 && count($chunk) % $chunkSize === 0) {
                yield $chunk;
                $chunk = [];
            }
        }

        if (empty($chunk) === false) {
            yield $chunk;
        }
    }
}
