<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use LogicException;

class TransmittedClassIsNotAModel extends LogicException implements BulkException
{
    public function __construct(string $className)
    {
        parent::__construct('Model [' . $className . '] has to implement BulkModel interface');
    }
}
