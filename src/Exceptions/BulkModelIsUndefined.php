<?php

namespace Lapaliv\BulkUpsert\Exceptions;

class BulkModelIsUndefined extends BulkException
{
    public function __construct()
    {
        parent::__construct('Model is undefined');
    }
}
