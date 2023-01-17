<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Update;

use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Tests\App\Collections\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\GetArticleCollectionForUpdateTestsFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

class ChunkSizeTest extends TestCase
{
    private const NUMBER_OF_ARTICLES = 5;
    private const CHUNK_SIZE = 1;

    private int $numberOfChunks = 0;

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

        $this->assertEquals(
            ceil(self::NUMBER_OF_ARTICLES / self::CHUNK_SIZE),
            $this->numberOfChunks,
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
     *     sut: BulkUpdate
     * }
     */
    private function arrange(string $model): array
    {
        $this->numberOfChunks = 0;

        /** @var GetArticleCollectionForUpdateTestsFeature $generateArticlesFeature */
        $generateArticlesFeature = $this->app->make(GetArticleCollectionForUpdateTestsFeature::class);
        $articles = $generateArticlesFeature->handle($model, self::NUMBER_OF_ARTICLES);

        return [
            'collection' => $articles,
            'sut' => $this->app->make(BulkUpdate::class)
                ->chunk(self::CHUNK_SIZE, function (array $chunk): void {
                    self::assertCount(self::CHUNK_SIZE, $chunk);
                    $this->numberOfChunks++;
                }),
        ];
    }
}
