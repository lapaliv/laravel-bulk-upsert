<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lapaliv\BulkUpsert\Tests\App\Builders\PostBuilder;
use Lapaliv\BulkUpsert\Tests\App\Builders\UserBuilder;
use Lapaliv\BulkUpsert\Tests\App\Factories\SqLiteCommentFactory;

/**
 * @internal
 *
 * @property-read SqLiteUser $user
 * @property-read SqLitePost $post
 *
 * @method static SqLiteCommentFactory factory($count = null, $state = [])
 */
final class SqLiteComment extends Comment
{
    protected $connection = 'sqlite';

    public function user(): BelongsTo|UserBuilder
    {
        return $this->belongsTo(SqLiteUser::class, 'user_id');
    }

    public function post(): BelongsTo|PostBuilder
    {
        return $this->belongsTo(SqLitePost::class, 'post_id', 'id');
    }

    public static function newFactory(): SqLiteCommentFactory
    {
        return new SqLiteCommentFactory();
    }
}
