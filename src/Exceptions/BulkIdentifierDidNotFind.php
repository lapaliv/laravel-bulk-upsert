<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use RuntimeException;

/**
 * Thrown when the unique attributes of a row cannot be found.
 *
 * It happens when the unique attributes are not given with the `uniqueBy` method and the
 * row does not have all of them.
 */
class BulkIdentifierDidNotFind extends RuntimeException implements BulkException
{
    /**
     * Create a new exception instance.
     *
     * @param mixed $row
     * @param array<int, callable|string[]> $uniqueAttributes
     *
     * @return void
     */
    public function __construct(private readonly mixed $row, private readonly array $uniqueAttributes)
    {
        parent::__construct(
            'Unique attributes did not find for the row. ' .
            'Please pass them via the `uniqueBy` method or ' .
            'turn off -ed events if you try to use `create`/`createOrAccumulate` methods'
        );
    }

    /**
     * Get the row which has no unique attributes.
     *
     * @return mixed
     */
    public function getRow(): mixed
    {
        return $this->row;
    }

    /**
     * Get the unique attributes which were looked for.
     *
     * @return array<int, callable|string[]>
     */
    public function getUniqueAttributes(): array
    {
        return $this->uniqueAttributes;
    }

    /**
     * Get the context of the exception for the logs.
     *
     * @return array
     *
     * @psalm-api
     */
    public function context(): array
    {
        return [
            'row' => $this->getRow(),
            'unique_attributes' => $this->getUniqueAttributes(),
        ];
    }
}
