<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Insert;

use Faker\Factory;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\App\Models\Article;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

class SelectColumnsWithTimestampsTest extends TestCase
{
    private const NUMBER_OF_ARTICLES = 3;

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
        // This part is described in the `assertChunk` method
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
     *     sut: \Lapaliv\BulkUpsert\BulkInsert,
     *     collection: Collection
     * }
     */
    private function arrange(string $model): array
    {
        $collection = $this->generateCollection($model);

        $actualSelectColumns = ['uuid', 'name'];
        $expectSelectColumns = [...$actualSelectColumns, (new $model())->getCreatedAtColumn()];

        $sut = $this->app->make(BulkInsert::class)
            ->select($actualSelectColumns)
            ->onInserted(
                fn (Collection $users) => $this->assertChunk($users, $expectSelectColumns)
            );

        return compact('sut', 'collection');
    }

    public function assertChunk(Collection $articles, array $expectSelectColumns): void
    {
        $articles->each(
            function (Article $article) use ($expectSelectColumns): void {
                $diff = array_diff(
                    array_keys($article->getAttributes()),
                    $expectSelectColumns
                );

                self::assertEmpty($diff, 'Article has extra columns');
            }
        );
    }

    private function generateCollection(string $model): Collection
    {
        $faker = Factory::create();
        $result = new Collection();

        for ($i = 0; $i < self::NUMBER_OF_ARTICLES; $i++) {
            $result->push(
                new $model([
                    'uuid' => $faker->uuid(),
                    'name' => $faker->text(50),
                ])
            );
        }

        return $result;
    }
}