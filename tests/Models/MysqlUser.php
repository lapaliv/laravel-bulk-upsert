<?php

namespace Lapaliv\BulkUpsert\Tests\Models;

class MysqlUser extends User
{
    protected $connection = 'mysql';
}