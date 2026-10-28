<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use RuntimeException;

/**
 * Thrown when a value cannot be converted into a scalar value.
 */
class BulkValueTypeIsNotSupported extends RuntimeException implements BulkException
{
    /**
     * Create a new exception instance.
     *
     * @param mixed $value
     *
     * @return void
     */
    public function __construct(private readonly mixed $value)
    {
        parent::__construct('Value type is not supported');
    }

    /**
     * Get the value which is not supported.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
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
            'value' => $this->getValue(),
        ];
    }
}
