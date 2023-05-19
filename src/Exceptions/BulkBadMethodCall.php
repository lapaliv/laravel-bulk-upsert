<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use BadMethodCallException;
use Lapaliv\BulkUpsert\Contracts\BulkException;

class BulkBadMethodCall extends BadMethodCallException implements BulkException
{
    public function __construct(string $className, string $methodName)
    {
        parent::__construct(
            sprintf(
                'Method %s::%s() is undefined',
                $className,
                $methodName
            )
        );
    }
}
