<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;

/**
 * @internal
 *
 * @method CommentCollection|PostgreSqlComment create($attributes = [], ?Model $parent = null)
 * @method CommentCollection|PostgreSqlComment make($attributes = [], ?Model $parent = null)
 * @method CommentCollection|PostgreSqlComment createMany(iterable $records)
 */
final class PostgreSqlCommentFactory extends CommentFactory
{
    protected $model = PostgreSqlComment::class;

    public function definition(): array
    {
        return array_merge(parent::definition(), [
            'user_id' => PostgreSqlUser::factory(),
            'post_id' => PostgreSqlPost::factory(),
        ]);
    }
}
