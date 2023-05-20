<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lapaliv\BulkUpsert\Tests\App\Builders\PostBuilder;
use Lapaliv\BulkUpsert\Tests\App\Builders\UserBuilder;
use Lapaliv\BulkUpsert\Tests\App\Factories\MySqlCommentFactory;

/**
 * @internal
 *
 * @property-read MySqlUser $user
 * @property-read MySqlPost $post
 *
 * @method static MySqlCommentFactory factory($count = null, $state = [])
 */
final class MySqlComment extends Comment
{
    protected $connection = 'mysql';

    public function user(): BelongsTo|UserBuilder
    {
        return $this->belongsTo(MySqlUser::class, 'user_id');
    }

    public function post(): BelongsTo|PostBuilder
    {
        return $this->belongsTo(MySqlPost::class, 'post_id', 'id');
    }

    public static function newFactory(): MySqlCommentFactory
    {
        return new MySqlCommentFactory();
    }
}
