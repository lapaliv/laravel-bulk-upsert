<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;

/**
 * @internal
 *
 * @method CommentCollection|MySqlComment create($attributes = [], ?Model $parent = null)
 * @method CommentCollection|MySqlComment make($attributes = [], ?Model $parent = null)
 * @method CommentCollection|MySqlComment createMany(iterable $records)
 */
final class MySqlCommentFactory extends CommentFactory
{
    protected $model = MySqlComment::class;

    public function definition(): array
    {
        return array_merge(parent::definition(), [
            'user_id' => MySqlUser::factory(),
            'post_id' => MySqlPost::factory(),
        ]);
    }
}
