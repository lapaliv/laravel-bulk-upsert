<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Lapaliv\BulkUpsert\Tests\App\Builders\ArticleBuilder;
use Lapaliv\BulkUpsert\Tests\App\Builders\CommentBuilder;
use Lapaliv\BulkUpsert\Tests\App\Collection\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Factories\PostgreSqlUserFactory;

/**
 * @internal
 *
 * @property-read CommentCollection $comments
 * @property-read ArticleCollection $article
 *
 * @method static PostgreSqlUserFactory factory($count = null, $state = [])
 */
final class PostgreSqlUser extends User
{
    protected $connection = 'pgsql';

    public function comments(): HasMany|CommentBuilder
    {
        return $this->hasMany(PostgreSqlComment::class, 'user_id');
    }

    public function articles(): HasMany|ArticleBuilder
    {
        return $this->hasMany(PostgreSqlArticle::class, 'user_id');
    }

    protected static function newFactory(): PostgreSqlUserFactory
    {
        return new PostgreSqlUserFactory();
    }
}
