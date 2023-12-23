<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lapaliv\BulkUpsert\Tests\App\Factories\MySqlArticleFactory;

/**
 * @property-read MySqlUser $user
 *
 * @method static MySqlArticleFactory factory($count = null, $state = [])
 */
class MySqlArticle extends Article
{
    protected $connection = 'mysql';

    public function user(): BelongsTo
    {
        return $this->belongsTo(MySqlUser::class);
    }

    public static function newFactory(): MySqlArticleFactory
    {
        return new MySqlArticleFactory();
    }
}
