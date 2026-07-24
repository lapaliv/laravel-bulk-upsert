<?php

namespace Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\App\Models\Comment;
use Tests\App\Models\Post;
use Tests\App\Models\User;

/**
 * The factory which builds {@see \Tests\App\Models\Comment} test models.
 *
 * A comment belongs to a user and to a post, and the factory creates both of them.
 * The tests use it mostly to check that the package touches the related models.
 *
 * @internal
 *
 * @method \Tests\App\Collection\CommentCollection|\Tests\App\Models\Comment create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method \Tests\App\Collection\CommentCollection|\Tests\App\Models\Comment make($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method \Tests\App\Collection\CommentCollection|\Tests\App\Models\Comment createMany(iterable $records)
 */
class CommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Tests\App\Models\Comment>
     */
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
