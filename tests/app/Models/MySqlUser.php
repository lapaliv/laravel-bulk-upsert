<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Lapaliv\BulkUpsert\Tests\App\Collections\MySqlUserCollection;
use Lapaliv\BulkUpsert\Tests\App\Collections\UserCollection;

class MySqlUser extends User
{
    protected $connection = 'mysql';

    public function newCollection(array $models = []): UserCollection
    {
        return new MySqlUserCollection($models);
    }
}
