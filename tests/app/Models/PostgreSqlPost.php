<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Lapaliv\BulkUpsert\Tests\App\Builders\CommentBuilder;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Factories\PostgreSqlPostFactory;

/**
 * @internal
 *
 * @property-read CommentCollection $comments
 *
 * @method static PostgreSqlPostFactory factory($count = null, $state = [])
 */
final class PostgreSqlPost extends Post
{
    protected $connection = 'pgsql';

    public function comments(): HasMany|CommentBuilder
    {
        return $this->hasMany(PostgreSqlComment::class, 'post_id', 'id');
    }

    public static function newFactory(): PostgreSqlPostFactory
    {
        return new PostgreSqlPostFactory();
    }
}
