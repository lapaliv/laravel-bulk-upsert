<?php

namespace Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\App\Models\Post;

/**
 * The factory which builds {@see \Tests\App\Models\Post} test models.
 *
 * A post has only a text column and the usual timestamps. The tests use it as the
 * related model of a comment and as a simple model without soft deletes.
 *
 * @internal
 *
 * @method \Tests\App\Models\Post|\Tests\App\Collection\PostCollection create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method \Tests\App\Models\Post|\Tests\App\Collection\PostCollection make($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method \Tests\App\Models\Post|\Tests\App\Collection\PostCollection createMany(iterable $records)
 * @method self|static count(?int $count)
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Tests\App\Models\Post>
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'text' => $this->faker->text(),
        ];
    }
}
