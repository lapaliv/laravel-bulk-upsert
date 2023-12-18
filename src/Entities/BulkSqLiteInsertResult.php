<?php

namespace Lapaliv\BulkUpsert\Entities;

use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;

/**
 * @internal
 */
class BulkSqLiteInsertResult implements BulkInsertResult
{
    public function __construct(private ?int $maxPrimaryBeforeInserting)
    {
        //
    }

    public function getRows(): ?array
    {
        return null;
    }

    public function getMaxPrimaryBeforeInserting(): null|int|string
    {
        return $this->maxPrimaryBeforeInserting;
    }
}
