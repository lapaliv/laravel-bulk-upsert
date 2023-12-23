<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteUser;

/**
 * @internal
 *
 * @method ArticleCollection|SqLiteArticle create($attributes = [], ?Model $parent = null)
 * @method ArticleCollection|SqLiteArticle make($attributes = [], ?Model $parent = null)
 * @method ArticleCollection|SqLiteArticle createMany(iterable $records)
 */
final class SqLiteArticleFactory extends ArticleFactory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SqLiteArticle>
     */
    protected $model = SqLiteArticle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return array_merge(parent::definition(), [
            'user_id' => SqLiteUser::factory(),
        ]);
    }
}
