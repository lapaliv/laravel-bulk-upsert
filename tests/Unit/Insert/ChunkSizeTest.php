<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Insert;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

class ChunkSizeTest extends TestCase
{
    private const NUMBER_OF_ROWS = 5;
    private const CHUNK_SIZE = 1;

    private int $numberOfChunks = 0;

    public function test(): void
    {
        [
            'collection' => $collection,
            'sut' => $sut,
        ] = $this->arrange();

        // act
        $sut->insert(MysqlUser::class, ['email'], $collection);

        // assert
        $this->assertEquals(
            ceil(self::NUMBER_OF_ROWS / self::CHUNK_SIZE),
            $this->numberOfChunks
        );
    }

    /**
     * @return array{
     *     collection: Collection,
     *     sut: BulkInsert
     * }
     */
    private function arrange(): array
    {
        $this->numberOfChunks = 0;

        $generateUserCollectionFeature = new GenerateUserCollectionFeature(MysqlUser::class);
        $collection = $generateUserCollectionFeature->handle(self::NUMBER_OF_ROWS);
        $sut = $this->app->make(BulkInsert::class)
            ->chunk(
                self::CHUNK_SIZE,
                function (array $chunk) {
                    $this->assertCount(self::CHUNK_SIZE, $chunk);

                    $this->numberOfChunks++;
                }
            );

        return compact('collection', 'sut');
    }
}
