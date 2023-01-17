<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Update;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Tests\App\Collections\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\GetArticleCollectionForUpdateTestsFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\Article;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

class TimestampFields extends TestCase
{
    private const NUMBER_OF_ARTICLES = 5;

    /**
     * @dataProvider data
     * @param string $model
     * @return void
     */
    public function test(string $model): void
    {
        [
            'collection' => $collection,
            'sut' => $sut,
        ] = $this->arrange($model);

        // act
        $sut->update($model, $collection);

        // assets
        $collection->each(
            function (Article $article): void {
                $this->assertDatabaseHas(
                    $article->getTable(),
                    [
                        'uuid' => $article->uuid,
                        'updated_at' => Carbon::now(),
                    ],
                    $article->getConnectionName()
                );
            }
        );
    }

    public function data(): array
    {
        return [
            [MySqlArticle::class],
            [PostgreSqlArticle::class],
        ];
    }

    /**
     * @param string $model
     * @return array{
     *     collection: ArticleCollection,
     *     sut: BulkUpdate,
     * }
     */
    private function arrange(string $model): array
    {
        /** @var GetArticleCollectionForUpdateTestsFeature $generateArticlesFeature */
        $generateArticlesFeature = $this->app->make(GetArticleCollectionForUpdateTestsFeature::class);
        $articles = $generateArticlesFeature->handle($model, self::NUMBER_OF_ARTICLES);

        Carbon::setTestNow(Carbon::now());

        return [
            'collection' => $articles,
            'sut' => $this->app->make(BulkUpdate::class),
        ];
    }
}
