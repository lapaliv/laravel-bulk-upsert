<?php

namespace Lapaliv\BulkUpsert\Tests\Models;

class PostgresUser extends User
{
    protected $connection = 'postgres';
}