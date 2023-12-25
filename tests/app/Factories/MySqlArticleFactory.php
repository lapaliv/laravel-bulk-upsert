<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;

/**
 * @internal
 *
 * @method ArticleCollection|MySqlArticle create($attributes = [], ?Model $parent = null)
 * @method ArticleCollection|MySqlArticle make($attributes = [], ?Model $parent = null)
 * @method ArticleCollection|MySqlArticle createMany(iterable $records)
 */
final class MySqlArticleFactory extends ArticleFactory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<MySqlArticle>
     */
    protected $model = MySqlArticle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return array_merge(parent::definition(), [
            'user_id' => MySqlUser::factory(),
        ]);
    }
}
