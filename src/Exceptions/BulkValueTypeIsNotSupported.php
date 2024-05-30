<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use RuntimeException;

class BulkValueTypeIsNotSupported extends RuntimeException implements BulkException
{
    public function __construct(private mixed $value)
    {
        parent::__construct('Value type is not supported');
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @return array
     *
     * @psalm-api
     */
    public function context(): array
    {
        return [
            'value' => $this->getValue(),
        ];
    }
}
