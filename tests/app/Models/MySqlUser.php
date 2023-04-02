<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Lapaliv\BulkUpsert\Tests\App\Factories\MySqlUserFactory;
use Lapaliv\BulkUpsert\Tests\App\Factories\UserFactory;

/**
 * @internal
 */
class MySqlUser extends User
{
    protected $connection = 'mysql';

    protected static function newFactory(): UserFactory
    {
        return new MySqlUserFactory();
    }
}
