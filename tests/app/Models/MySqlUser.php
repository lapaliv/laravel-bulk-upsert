<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Lapaliv\BulkUpsert\Tests\App\Builders\ArticleBuilder;
use Lapaliv\BulkUpsert\Tests\App\Builders\CommentBuilder;
use Lapaliv\BulkUpsert\Tests\App\Collection\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Factories\MySqlUserFactory;

/**
 * @internal
 *
 * @property-read CommentCollection $comments
 * @property-read ArticleCollection $articles
 *
 * @method static MySqlUserFactory factory($count = null, $state = [])
 */
final class MySqlUser extends User
{
    protected $connection = 'mysql';

    public function comments(): HasMany|CommentBuilder
    {
        return $this->hasMany(MySqlComment::class, 'user_id');
    }

    public function articles(): HasMany|ArticleBuilder
    {
        return $this->hasMany(MySqlArticle::class, 'user_id');
    }

    protected static function newFactory(): MySqlUserFactory
    {
        return new MySqlUserFactory();
    }
}
