<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use RuntimeException;

class BulkIdentifierDidNotFind extends RuntimeException implements BulkException
{
    public function __construct(private mixed $row, private array $uniqueAttributes)
    {
        parent::__construct('Unique attributes did not find for the row');
    }

    public function getRow(): mixed
    {
        return $this->row;
    }

    public function getUniqueAttributes(): array
    {
        return $this->uniqueAttributes;
    }

    public function context(): array
    {
        return [
            'row' => $this->getRow(),
            'unique_attributes' => $this->getUniqueAttributes(),
        ];
    }
}
