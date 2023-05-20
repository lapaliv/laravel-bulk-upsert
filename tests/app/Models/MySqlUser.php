<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Lapaliv\BulkUpsert\Tests\App\Builders\CommentBuilder;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Factories\MySqlUserFactory;

/**
 * @internal
 *
 * @property-read CommentCollection $comments
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

    protected static function newFactory(): MySqlUserFactory
    {
        return new MySqlUserFactory();
    }
}
