<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Lapaliv\BulkUpsert\Tests\App\Collections\PostgreSqlUserCollection;
use Lapaliv\BulkUpsert\Tests\App\Collections\UserCollection;

class PostgreSqlUser extends User
{
    protected $connection = 'postgres';

    public function newCollection(array $models = []): UserCollection
    {
        return new PostgreSqlUserCollection($models);
    }
}
