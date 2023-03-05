<?php

namespace Lapaliv\BulkUpsert\Exceptions;

class BulkModelWasNotTransferred extends BulkException
{
    public function __construct()
    {
        parent::__construct('Model was not transferred. Please use Bulk::model() before');
    }
}
