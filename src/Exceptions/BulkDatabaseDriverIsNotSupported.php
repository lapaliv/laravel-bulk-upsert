<?php

namespace Lapaliv\BulkUpsert\Exceptions;

class BulkDatabaseDriverIsNotSupported extends BulkException
{
    public function __construct(private string $driverName)
    {
        parent::__construct('Database driver is not supported');
    }

    public function getDriverName(): string
    {
        return $this->driverName;
    }
}