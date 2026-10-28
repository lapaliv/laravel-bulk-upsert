<?php

namespace Lapaliv\BulkUpsert\Drivers\MySql;

use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Contracts\BulkInsertedIndexes;

/**
 * The result of a MySQL insert query.
 *
 * @internal
 */
readonly class MySqlInsertResult implements BulkInsertResult, BulkInsertedIndexes
{
    /**
     * Create a new result instance.
     *
     * @param int|null $maxPrimaryBeforeInserting
     *
     * @return void
     */
    public function __construct(
        private ?int $maxPrimaryBeforeInserting,
        private ?array $insertedIndexes = null,
    )
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

    /**
     * Get the positions of the rows which the statement has created.
     *
     * @return list<int>|null
     */
    public function getInsertedIndexes(): ?array
    {
        /** @var list<int>|null */
        return $this->insertedIndexes;
    }
}
