<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Lapaliv\BulkUpsert\Tests\App\Factories\SqLiteStoryFactory;

/**
 * @method static SqLiteStoryFactory factory($count = null, $state = [])
 */
class SqLiteStory extends Story
{
    protected $connection = 'sqlite';

    public static function newFactory(): SqLiteStoryFactory
    {
        return new SqLiteStoryFactory();
    }
}
