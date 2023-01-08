<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Update;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Tests\Collections\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\Features\GetArticleCollectionForUpdateTestsFeature;
use Lapaliv\BulkUpsert\Tests\Models\Article;
use Lapaliv\BulkUpsert\Tests\Models\MysqlArticle;
use Lapaliv\BulkUpsert\Tests\Models\PostgresArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

class DateFieldsTest extends TestCase
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

        // assert
        $collection->each(
            function (Article $article): void {
                $this->assertDatabaseHas(
                    $article->getTable(),
                    [
                        'uuid' => $article->uuid,
                        'date' => Carbon::parse($article->date)->toDateString(),
                        'microseconds' => Carbon::parse($article->microseconds)->format('Y-m-d H:i:s.u'),
                    ],
                    $article->getConnectionName()
                );

                $this->assertDatabaseMissing(
                    $article->getTable(),
                    [
                        'uuid' => $article->uuid,
                        'microseconds' => Carbon::parse($article->microseconds)->format('Y-m-d H:i:s.000000'),
                    ],
                    $article->getConnectionName()
                );
            }
        );
    }

    /**
     * @return string[][]
     */
    public function data(): array
    {
        return [
            [MysqlArticle::class],
            [PostgresArticle::class],
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

        return [
            'collection' => $articles,
            'sut' => $this->app->make(BulkUpdate::class),
        ];
    }
}
