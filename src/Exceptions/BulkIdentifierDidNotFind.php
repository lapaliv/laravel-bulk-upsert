<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use RuntimeException;

class BulkIdentifierDidNotFind extends RuntimeException implements BulkException
{
    public function __construct(private mixed $row, private array $uniqueAttributes)
    {
        parent::__construct(
            'Unique attributes did not find for the row. ' .
            'Please pass them via the `uniqueBy` method or ' .
            'turn off -ed events if you try to use `create`/`createOrAccumulate` methods'
        );
    }

    public function getRow(): mixed
    {
        return $this->row;
    }

    public function getUniqueAttributes(): array
    {
        return $this->uniqueAttributes;
    }

    /**
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
