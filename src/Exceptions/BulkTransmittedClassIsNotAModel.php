<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use LogicException;

class BulkTransmittedClassIsNotAModel extends LogicException implements BulkException
{
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
