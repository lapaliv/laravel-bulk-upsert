<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lapaliv\BulkUpsert\Tests\App\Factories\PostgreSqlArticleFactory;

/**
 * @property-read PostgreSqlUser $user
 *
 * @method static PostgreSqlArticleFactory factory($count = null, $state = [])
 */
class PostgreSqlArticle extends Article
{
    protected $connection = 'pgsql';

    public function user(): BelongsTo
    {
        return $this->belongsTo(PostgreSqlUser::class);
    }

    public static function newFactory(): PostgreSqlArticleFactory
    {
        return new PostgreSqlArticleFactory();
    }
}
