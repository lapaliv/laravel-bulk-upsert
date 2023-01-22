<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

class MySqlEntityWithoutAutoIncrement extends Entity
{
    public $incrementing = false;

    protected $connection = 'mysql';
    protected $table = 'entities_without_auto_increment';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
}
