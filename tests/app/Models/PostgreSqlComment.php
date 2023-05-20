<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lapaliv\BulkUpsert\Tests\App\Builders\PostBuilder;
use Lapaliv\BulkUpsert\Tests\App\Builders\UserBuilder;
use Lapaliv\BulkUpsert\Tests\App\Factories\PostgreSqlCommentFactory;

/**
 * @internal
 *
 * @property-read PostgreSqlUser $user
 * @property-read PostgreSqlPost $post
 *
 * @method static PostgreSqlCommentFactory factory($count = null, $state = [])
 */
final class PostgreSqlComment extends Comment
{
    protected $connection = 'pgsql';

    public function user(): BelongsTo|UserBuilder
    {
        return $this->belongsTo(PostgreSqlUser::class, 'user_id');
    }

    public function post(): BelongsTo|PostBuilder
    {
        return $this->belongsTo(PostgreSqlPost::class, 'post_id', 'id');
    }

    public static function newFactory(): PostgreSqlCommentFactory
    {
        return new PostgreSqlCommentFactory();
    }
}
