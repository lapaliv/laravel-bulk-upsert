<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use LogicException;

/**
 * Thrown when the package has no bulk driver for the database driver of the connection.
 */
class BulkDriverIsNotSupported extends LogicException implements BulkException
{
    /**
     * Create a new exception instance.
     *
     * @param string $driverName
     *
     * @return void
     */
    public function __construct(string $driverName)
    {
        parent::__construct('Database driver ' . $driverName . ' is not supported');
    }
}
