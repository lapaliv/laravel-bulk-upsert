<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Insert;

use Faker\Factory;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\Collections\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\Models\Article;
use Lapaliv\BulkUpsert\Tests\Models\MysqlArticle;
use Lapaliv\BulkUpsert\Tests\Models\PostgresArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

class NotAlignedAttributesTest extends TestCase
{
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
        $sut->insert($collection, ['uuid']);

        // assert
        $collection->each(
            function (Article $article): void {
                $this->assertDatabaseHas(
                    $article->getTable(),
                    [
                        'uuid' => $article->uuid,
                        'name' => $article->name,
                        'content' => $article->content ?? null,
                        'is_new' => $article->is_new ?? true,
                    ],
                    $article->getConnectionName(),
                );
            }
        );
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
     *     sut: \Lapaliv\BulkUpsert\BulkInsert,
     *     collection: \Lapaliv\BulkUpsert\Tests\Collections\ArticleCollection
     * }
     */
    private function arrange(string $model): array
    {
        $collection = $this->generateCollection($model);
        $sut = new BulkInsert($model);

        return compact('sut', 'collection');
    }

    private function generateCollection(string $model): ArticleCollection
    {
        $fake = Factory::create();

        return new ArticleCollection([
            new $model([
                'uuid' => $fake->uuid(),
                'name' => $fake->text(50),
            ]),
            new $model([
                'uuid' => $fake->uuid(),
                'name' => $fake->text(50),
                'content' => $fake->text(),
                'is_new' => false,
            ])
        ]);
    }
}
