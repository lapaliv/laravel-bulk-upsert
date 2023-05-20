<?php

namespace Lapaliv\BulkUpsert\Contracts;

interface BulkInsertResult
{
    /**
     * Returns all inserted rows if it's possible.
     * If it's not possible then it should return `null`.
     *
     * @return array|null
     */
    public function getRows(): ?array;

    /**
     * Returns the max primary key which the table has before inserting.
     * If the table doesn't have single primary then it should return `null`.
     *
     * @return int|string|null
     */
    public function getMaxPrimaryBeforeInserting(): null|int|string;
}
