<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Lapaliv\BulkUpsert\Tests\App\Factories\PostgreSqlStoryFactory;

/**
 * @method static PostgreSqlStoryFactory factory($count = null, $state = [])
 */
class PostgreSqlStory extends Story
{
    protected $connection = 'pgsql';

    public static function newFactory(): PostgreSqlStoryFactory
    {
        return new PostgreSqlStoryFactory();
    }
}
