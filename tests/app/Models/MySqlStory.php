<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Lapaliv\BulkUpsert\Tests\App\Factories\MySqlStoryFactory;

/**
 * @method static MySqlStoryFactory factory($count = null, $state = [])
 */
class MySqlStory extends Story
{
    protected $connection = 'mysql';

    public static function newFactory(): MySqlStoryFactory
    {
        return new MySqlStoryFactory();
    }
}
