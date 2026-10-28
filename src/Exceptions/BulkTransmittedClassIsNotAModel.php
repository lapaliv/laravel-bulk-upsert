<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use LogicException;

/**
 * Thrown when the given class is not an Eloquent model.
 */
class BulkTransmittedClassIsNotAModel extends LogicException implements BulkException
{
    /**
     * Create a new exception instance.
     *
     * @param string $className
     *
     * @return void
     */
    public function __construct(string $className)
    {
        parent::__construct(
            sprintf(
                'Model %s has to implement BulkModel interface',
                $className
            )
        );
    }
}
