<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use RuntimeException;

class BulkAttributeTypeIsNotScalar extends RuntimeException implements BulkException
{
    public function __construct(string $name)
    {
        parent::__construct('The attribute ' . $name . ' has not scalar type');
    }
}
