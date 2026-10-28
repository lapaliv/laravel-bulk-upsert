<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use RuntimeException;

/**
 * Thrown when an attribute of a model cannot be converted into a scalar value.
 */
class BulkAttributeTypeIsNotScalar extends RuntimeException implements BulkException
{
    /**
     * Create a new exception instance.
     *
     * @param string $name
     *
     * @return void
     */
    public function __construct(string $name)
    {
        parent::__construct('The attribute ' . $name . ' has not scalar type');
    }
}
