<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use RuntimeException;

/**
 * Thrown when the bulk is used before a model is given to it.
 *
 * @psalm-api
 */
class BulkModelWasNotTransferred extends RuntimeException implements BulkException
{
    /**
     * Create a new exception instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('A model was not transferred. Please use Bulk::model() before');
    }
}
