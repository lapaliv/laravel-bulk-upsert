<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteComment;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLitePost;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteUser;

/**
 * @internal
 *
 * @method CommentCollection|SqLiteComment create($attributes = [], ?Model $parent = null)
 * @method CommentCollection|SqLiteComment make($attributes = [], ?Model $parent = null)
 * @method CommentCollection|SqLiteComment createMany(iterable $records)
 */
final class SqLiteCommentFactory extends CommentFactory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SqLiteComment>
     */
    protected $model = SqLiteComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return array_merge(parent::definition(), [
            'user_id' => SqLiteUser::factory(),
            'post_id' => SqLitePost::factory(),
        ]);
    }
}
