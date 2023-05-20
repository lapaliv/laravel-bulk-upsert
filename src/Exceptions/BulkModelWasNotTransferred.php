<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use RuntimeException;

class BulkModelWasNotTransferred extends RuntimeException implements BulkException
{
    public function __construct()
    {
        parent::__construct('A model was not transferred. Please use Bulk::model() before');
    }
}
