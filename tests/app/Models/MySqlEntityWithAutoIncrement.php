<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Lapaliv\BulkUpsert\Tests\App\Collections\EntityWithAutoIncrementCollection;

/**
 * @property int $id
 */
class MySqlEntityWithAutoIncrement extends Entity
{
    protected $connection = 'mysql';
    protected $table = 'entities_with_auto_increment';

    public function newCollection(array $models = []): EntityWithAutoIncrementCollection
    {
        return new EntityWithAutoIncrementCollection($models);
    }
}
