<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Lapaliv\BulkUpsert\Tests\App\Builders\CommentBuilder;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Factories\SqLitePostFactory;

/**
 * @internal
 *
 * @property-read CommentCollection $comments
 *
 * @method static SqLitePostFactory factory($count = null, $state = [])
 */
final class SqLitePost extends Post
{
    protected $connection = 'sqlite';

    public function comments(): HasMany|CommentBuilder
    {
        return $this->hasMany(SqLiteComment::class, 'post_id', 'id');
    }

    public static function newFactory(): SqLitePostFactory
    {
        return new SqLitePostFactory();
    }
}
