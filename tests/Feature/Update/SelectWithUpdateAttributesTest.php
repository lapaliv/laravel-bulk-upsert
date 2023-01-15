<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Update;

use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Tests\Collections\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\Features\GetArticleCollectionForUpdateTestsFeature;
use Lapaliv\BulkUpsert\Tests\Models\Article;
use Lapaliv\BulkUpsert\Tests\Models\MysqlArticle;
use Lapaliv\BulkUpsert\Tests\Models\PostgresArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

class SelectWithUpdateAttributesTest extends TestCase
{
    private const NUMBER_OF_ARTICLES = 5;
    private const SELECT_COLUMNS = ['name'];

    /**
     * @dataProvider data
     * @param string $model
     * @return void
     */
    public function test(string $model): void
    {
        [
            'articles' => $articles,
            'sut' => $sut,
            'updateColumns' => $updateColumns,
        ] = $this->arrange($model);

        // act
        $sut->update($model, $articles, ['uuid'], $updateColumns);

        // asserts
        // This parts describes in the `assertCollection` method
    }

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
     *     collection: array[],
     *     sut: BulkUpdate,
     *     updateColumns: string[],
     * }
     */
    private function arrange(string $model): array
    {
        /** @var GetArticleCollectionForUpdateTestsFeature $generateArticlesFeature */
        $generateArticlesFeature = $this->app->make(GetArticleCollectionForUpdateTestsFeature::class);
        $articles = $generateArticlesFeature->handle($model, self::NUMBER_OF_ARTICLES);

        return [
            'articles' => $articles->toArray(),
            'sut' => $this->app->make(BulkUpdate::class)
                ->select(self::SELECT_COLUMNS)
                ->onUpdated(
                    fn(ArticleCollection $articles) => $this->assertCollection($articles)
                ),
            'updateColumns' => self::SELECT_COLUMNS,
        ];
    }

    private function assertCollection(ArticleCollection $collection): void
    {
        $collection->each(
            static function (Article $article): void {
                // `uuid` can't be null because it's the unique attribute
                self::assertNotNull($article->uuid);
                self::assertNotNull($article->name);

                self::assertNull($article->content);
                self::assertNull($article->is_new);
                self::assertNull($article->date);
                self::assertNull($article->microseconds);

                self::assertNull($article->created_at);
                self::assertNotNull($article->updated_at);
            }
        );
    }
}
