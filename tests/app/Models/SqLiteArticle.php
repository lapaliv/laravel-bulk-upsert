<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lapaliv\BulkUpsert\Tests\App\Factories\SqLiteArticleFactory;

/**
 * @property-read SqLiteUser $user
 *
 * @method static SqLiteArticleFactory factory($count = null, $state = [])
 */
class SqLiteArticle extends Article
{
    protected $connection = 'sqlite';

    public function user(): BelongsTo
    {
        return $this->belongsTo(SqLiteUser::class);
    }

    public static function newFactory(): SqLiteArticleFactory
    {
        return new SqLiteArticleFactory();
    }
}
