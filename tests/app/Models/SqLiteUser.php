<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Lapaliv\BulkUpsert\Tests\App\Builders\CommentBuilder;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Factories\SqLiteUserFactory;

/**
 * @internal
 *
 * @property-read CommentCollection $comments
 *
 * @method static SqLiteUserFactory factory($count = null, $state = [])
 */
final class SqLiteUser extends User
{
    protected $connection = 'sqlite';

    public function comments(): HasMany|CommentBuilder
    {
        return $this->hasMany(SqLiteComment::class, 'user_id');
    }

    protected static function newFactory(): SqLiteUserFactory
    {
        return new SqLiteUserFactory();
    }
}
