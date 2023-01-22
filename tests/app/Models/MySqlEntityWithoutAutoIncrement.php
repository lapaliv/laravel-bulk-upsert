<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Lapaliv\BulkUpsert\Tests\App\Collections\EntityWithoutAutoIncrementCollection;

class MySqlEntityWithoutAutoIncrement extends Entity
{
    public $incrementing = false;

    protected $connection = 'mysql';
    protected $table = 'entities_without_auto_increment';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';

    public function newCollection(array $models = []): EntityWithoutAutoIncrementCollection
    {
        return new EntityWithoutAutoIncrementCollection($models);
    }
}
