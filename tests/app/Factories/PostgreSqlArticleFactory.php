<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;

/**
 * @internal
 *
 * @method ArticleCollection|PostgreSqlArticle create($attributes = [], ?Model $parent = null)
 * @method ArticleCollection|PostgreSqlArticle make($attributes = [], ?Model $parent = null)
 * @method ArticleCollection|PostgreSqlArticle createMany(iterable $records)
 */
final class PostgreSqlArticleFactory extends ArticleFactory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PostgreSqlArticle>
     */
    protected $model = PostgreSqlArticle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return array_merge(parent::definition(), [
            'user_id' => PostgreSqlUser::factory(),
        ]);
    }
}
