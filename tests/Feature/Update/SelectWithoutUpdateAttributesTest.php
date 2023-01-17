<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Update;

use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Tests\App\Collections\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\GetArticleCollectionForUpdateTestsFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\Article;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

class SelectWithoutUpdateAttributesTest extends TestCase
{
    private const NUMBER_OF_ARTICLES = 5;
    private const SELECT_COLUMNS = ['name', 'content'];

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
        ] = $this->arrange($model);

        // act
        $sut->update($model, $articles);

        // asserts
        // This parts describes in the `assertCollection` method
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
     *     collection: array[],
     *     sut: BulkUpdate,
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
                    fn (ArticleCollection $articles) => $this->assertCollection($articles)
                ),
        ];
    }

    private function assertCollection(ArticleCollection $collection): void
    {
        $collection->each(
            static function (Article $article): void {
                self::assertNotNull($article->uuid);
                self::assertNotNull($article->name);

                self::assertNotNull($article->content);
                self::assertNotNull($article->is_new);
                self::assertNotNull($article->date);
                self::assertNotNull($article->microseconds);

                self::assertNotNull($article->created_at);
                self::assertNotNull($article->updated_at);
            }
        );
    }
}
