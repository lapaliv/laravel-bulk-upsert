<?php

namespace Lapaliv\BulkUpsert;

trait BulkBuilderTrait
{
    public function bulk(): Bulk
    {
        return new Bulk($this->getModel());
    }

//    abstract public function selectAndUpdateMany(array $unique): void;
//    abstract public function selectAndUpdateMany(array $unique): void;
}
