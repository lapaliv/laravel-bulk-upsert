<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use RuntimeException;

/**
 * Thrown when an upsert has sent new rows to the database, but has neither created them nor
 * found them afterwards by their unique attributes, so their saving is not confirmed.
 *
 * Possible causes: a duplicate key of another unique index than the one given to
 * `uniqueBy()`, on MySQL a duplicate key raised by a trigger in another table, a value which
 * the database changes or compares differently (collation), a scope which hides the row.
 *
 * The other rows of the chunk have already been written by their statements; an outer
 * transaction decides whether they stay. The listed rows stay in the accumulated storage, so
 * a later flush saves them once the cause is fixed, or reports them again.
 */
class BulkRowsWereNotSaved extends RuntimeException implements BulkException
{
    /**
     * Create a new exception instance.
     *
     * @param list<\Illuminate\Database\Eloquent\Model> $rows The models of the rows whose saving could not be confirmed.
     *
     * @return void
     */
    public function __construct(private readonly array $rows)
    {
        parent::__construct(sprintf(
            '%d row(s) of the upsert were sent but neither created nor found by the unique attributes afterwards, '
            . 'so their saving is not confirmed (e.g. a duplicate key of another unique index skipped them).',
            count($rows)
        ));
    }

    /**
     * Get the models of the rows whose saving could not be confirmed.
     *
     * @return list<\Illuminate\Database\Eloquent\Model>
     *
     * @psalm-api
     */
    public function getRows(): array
    {
        return $this->rows;
    }
}
