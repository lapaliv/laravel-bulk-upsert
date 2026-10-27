<?php

namespace Lapaliv\BulkUpsert\Contracts;

/**
 * An insert result which knows exactly which rows the statement has created.
 *
 * It is optional for the drivers: without it, the library guesses the created rows from the
 * primary keys or the creation time, which a row inserted meanwhile by somebody else can fool.
 */
interface BulkInsertedIndexes
{
    /**
     * Get the positions (from zero, in the order of the inserted values) of the rows which the
     * statement has created, or null when the driver does not know it (e.g. `INSERT IGNORE`).
     *
     * @return list<int>|null
     */
    public function getInsertedIndexes(): ?array;
}
