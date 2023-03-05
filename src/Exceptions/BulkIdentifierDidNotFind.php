<?php

namespace Lapaliv\BulkUpsert\Exceptions;

class BulkIdentifierDidNotFind extends BulkException
{
    public function __construct(private mixed $row, private array $identifiers)
    {
        parent::__construct('Identifier did not find for the row');
    }

    public function getRow(): mixed
    {
        return $this->row;
    }

    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }

    public function context(): array
    {
        return [
            'row' => $this->row,
            'identifiers' => $this->identifiers,
        ];
    }
}
