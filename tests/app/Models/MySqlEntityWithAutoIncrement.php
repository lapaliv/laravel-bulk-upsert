<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

/**
 * @property-read int $id
 */
class MySqlEntityWithAutoIncrement extends Entity
{
    protected $connection = 'mysql';
    protected $table = 'entities_with_auto_increment';
}
