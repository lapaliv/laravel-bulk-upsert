<?php

namespace Lapaliv\BulkUpsert\Exceptions;

class BulkAttributeTypeIsNotScalar extends BulkException
{
    public function __construct(private string $name)
    {
        parent::__construct('Attribute type is not scalar');
    }

    public function getName(): string
    {
        return $this->name;
    }
}
