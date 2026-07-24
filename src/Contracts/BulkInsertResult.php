<?php

namespace Lapaliv\BulkUpsert\Contracts;

/**
 * The result of an insert query which helps to find the inserted rows again.
 *
 * Every dialect gives back what it can: the rows themselves or the biggest
 * primary key which the table had before inserting.
 */
interface BulkInsertResult
{
    /**
     * Get all the inserted rows.
     *
     * The method returns `null` when the dialect cannot give the rows back.
     *
     * @return array|null
     */
    public function getRows(): ?array;

    /**
     * Get the biggest primary key which the table had before inserting.
     *
     * The method returns `null` when the table does not have a single primary key.
     *
     * @return int|string|null
     */
    public function getMaxPrimaryBeforeInserting(): null|int|string;
}
