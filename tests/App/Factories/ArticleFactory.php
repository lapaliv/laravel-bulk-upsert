<?php

namespace Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\App\Models\Article;
use Tests\App\Models\User;

/**
 * The factory which builds {@see \Tests\App\Models\Article} test models.
 *
 * An article has a uuid as its primary key, so the tests use it to check the bulk
 * methods on a model which does not have an auto incrementing key. Every article also
 * gets its own user.
 *
 * @internal
 *
 * @method \Tests\App\Collection\ArticleCollection|\Tests\App\Models\Article create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method \Tests\App\Collection\ArticleCollection|\Tests\App\Models\Article make($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method \Tests\App\Collection\ArticleCollection|\Tests\App\Models\Article createMany(iterable $records)
 */
class ArticleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Tests\App\Models\Article>
     */
    protected $model = Article::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'title' => $this->faker->text(50),
            'user_id' => User::factory(),
        ];
    }
}
