<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\Article;
use Lapaliv\BulkUpsert\Tests\App\Models\User;

/**
 * @internal
 *
 * @method ArticleCollection|Article create($attributes = [], ?Model $parent = null)
 * @method ArticleCollection|Article make($attributes = [], ?Model $parent = null)
 * @method ArticleCollection|Article createMany(iterable $records)
 */
class ArticleFactory extends Factory
{
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
