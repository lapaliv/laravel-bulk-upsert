<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use LogicException;

class BulkDriverIsNotSupported extends LogicException implements BulkException
{
    public function __construct(string $driverName)
    {
        parent::__construct('Database driver ' . $driverName . ' is not supported');
    }
}
