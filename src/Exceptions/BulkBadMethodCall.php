<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use BadMethodCallException;
use Lapaliv\BulkUpsert\Contracts\BulkException;

/**
 * Thrown when an undefined method of the package is called.
 */
class BulkBadMethodCall extends BadMethodCallException implements BulkException
{
    /**
     * Create a new exception instance.
     *
     * @param string $className
     * @param string $methodName
     *
     * @return void
     */
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
