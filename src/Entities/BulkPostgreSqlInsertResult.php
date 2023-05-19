<?php

namespace Lapaliv\BulkUpsert\Entities;

use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;

class BulkPostgreSqlInsertResult implements BulkInsertResult
{
    public function __construct(private array $rows)
    {
        //
    }

    public function getRows(): ?array
    {
        return $this->rows;
    }

    public function getMaxPrimaryBeforeInserting(): null|int|string
    {
        return null;
    }
}
