<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Insert;

use Faker\Factory;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\App\Collections\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

class OnInsertedCallbackTest extends TestCase
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
            'sut' => $sut,
            'collection' => $collection,
        ] = $this->arrange($model);

        // act
        $sut->insert($model, ['uuid'], $collection);

        // assert
        self::assertEquals(
            ceil(self::NUMBER_OF_ARTICLES / self::CHUNK_SIZE),
            $this->numberOfChunks
        );
    }

    public function data(): array
    {
        return [
            [MySqlArticle::class],
            [PostgreSqlArticle::class],
        ];
    }

    public function assertChunk(ArticleCollection $articles): void
    {
        self::assertCount(self::CHUNK_SIZE, $articles);

        $this->numberOfChunks++;
    }

    /**
     * @param string $model
     * @return array{
     *     collection: \Illuminate\Database\Eloquent\Collection,
     *     sut: \Lapaliv\BulkUpsert\BulkInsert
     * }
     */
    private function arrange(string $model): array
    {
        $collection = $this->generateCollection($model);

        $sut = $this->app->make(BulkInsert::class)
            ->chunk(self::CHUNK_SIZE)
            ->onInserted([$this, 'assertChunk']);

        return compact('sut', 'collection');
    }

    private function generateCollection(string $model): Collection
    {
        $fake = Factory::create();
        $result = new Collection();

        for ($i = 0; $i < self::NUMBER_OF_ARTICLES; $i++) {
            $result->push(
                new $model([
                    'uuid' => $fake->uuid(),
                    'name' => $fake->text(50),
                    'content' => $fake->text(),
                ])
            );
        }

        return $result;
    }
}