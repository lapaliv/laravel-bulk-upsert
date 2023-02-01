<?php

namespace Lapaliv\BulkUpsert\Exceptions;

class BulkValueTypeIsNotSupported extends BulkException
{
    public function __construct(private mixed $value)
    {
        parent::__construct('Value type is not supported');
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
