<?php

namespace Lapaliv\BulkUpsert\Drivers\MySql;

use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;

/**
 * The result of a MySQL insert query.
 *
 * @internal
 */
readonly class MySqlInsertResult implements BulkInsertResult
{
    /**
     * Create a new result instance.
     *
     * @param int|null $maxPrimaryBeforeInserting
     *
     * @return void
     */
    public function __construct(private ?int $maxPrimaryBeforeInserting)
    {
        //
    }

    /**
     * Get the inserted rows.
     *
     * MySQL cannot return them, so the method always gives `null`.
     *
     * @return array|null
     */
    public function getRows(): ?array
    {
        return null;
    }

    /**
     * Get the biggest primary key which the table had before inserting.
     *
     * @return int|string|null
     */
    public function getMaxPrimaryBeforeInserting(): null|int|string
    {
        return $this->maxPrimaryBeforeInserting;
    }
}
