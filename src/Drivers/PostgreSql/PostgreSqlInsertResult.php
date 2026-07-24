<?php

namespace Lapaliv\BulkUpsert\Drivers\PostgreSql;

use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;

/**
 * The result of a PostgreSQL insert query.
 *
 * @internal
 */
readonly class PostgreSqlInsertResult implements BulkInsertResult
{
    /**
     * Create a new result instance.
     *
     * @param array $rows
     *
     * @return void
     */
    public function __construct(private array $rows)
    {
        //
    }

    /**
     * Get the rows which the `returning` clause gave back.
     *
     * @return array|null
     */
    public function getRows(): ?array
    {
        return $this->rows;
    }

    /**
     * Get the biggest primary key which the table had before inserting.
     *
     * The rows are already known here, so the method always gives `null`.
     *
     * @return int|string|null
     */
    public function getMaxPrimaryBeforeInserting(): null|int|string
    {
        return null;
    }
}
