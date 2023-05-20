<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Lapaliv\BulkUpsert\Tests\App\Builders\CommentBuilder;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Factories\MySqlPostFactory;

/**
 * @internal
 *
 * @property-read CommentCollection $comments
 *
 * @method static MySqlPostFactory factory($count = null, $state = [])
 */
final class MySqlPost extends Post
{
    protected $connection = 'mysql';

    public function comments(): HasMany|CommentBuilder
    {
        return $this->hasMany(MySqlComment::class, 'post_id', 'id');
    }

    public static function newFactory(): MySqlPostFactory
    {
        return new MySqlPostFactory();
    }
}
