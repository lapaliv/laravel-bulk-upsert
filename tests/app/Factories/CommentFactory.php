<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\Comment;
use Lapaliv\BulkUpsert\Tests\App\Models\Post;
use Lapaliv\BulkUpsert\Tests\App\Models\User;

/**
 * @internal
 *
 * @method CommentCollection|Comment create($attributes = [], ?Model $parent = null)
 * @method CommentCollection|Comment make($attributes = [], ?Model $parent = null)
 * @method CommentCollection|Comment createMany(iterable $records)
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'text' => $this->faker->text(),
        ];
    }
}
