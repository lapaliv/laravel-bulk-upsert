<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Insert;

use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

class ChunkSizeTest extends TestCase
{
    private const NUMBER_OF_ROWS = 5;
    private const CHUNK_SIZE = 1;

    private int $numberOfChunks = 0;

    /**
     * @return void
     */
    public function test(): void
    {
        [
            'collection' => $collection,
            'sut' => $sut,
        ] = $this->arrange();

        // act
        $sut->insert($collection, ['email']);

        // assert
        $this->assertEquals(
            ceil(self::NUMBER_OF_ROWS / self::CHUNK_SIZE),
            $this->numberOfChunks
        );
    }

    /**
     * @return array{
     *     collection: \Illuminate\Database\Eloquent\Collection,
     *     sut: \Lapaliv\BulkUpsert\BulkInsert
     * }
     */
    private function arrange(): array
    {
        $generateUserCollectionFeature = new GenerateUserCollectionFeature(MysqlUser::class);
        $collection = $generateUserCollectionFeature->handle(self::NUMBER_OF_ROWS);
        $sut = (new BulkInsert(MysqlUser::class))
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
