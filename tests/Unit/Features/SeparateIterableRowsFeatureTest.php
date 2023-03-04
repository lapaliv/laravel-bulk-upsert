<?php

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;

final class SeparateIterableRowsFeatureTest extends UnitTestCase
{
    /**
     * @param int $chunkSize
     * @param int $limit
     * @return void
     * @dataProvider dataProvider
     */
    public function test(int $chunkSize, int $limit): void
    {
        // arrange
        $data = array_fill(0, $limit, null);

        /** @var SeparateIterableRowsFeature $sut */
        $sut = $this->app->make(SeparateIterableRowsFeature::class);

        // act
        $generator = $sut->handle($chunkSize, $data);

        // assert
        $numberOfChunks = 0;
        foreach($generator as $chunk){
            self::assertLessThanOrEqual($chunkSize, count($chunk));
            $numberOfChunks ++;
        }

        self::assertEquals(ceil($limit / $chunkSize), $numberOfChunks);
    }

    /**
     * @return int[][]
     */
    public function dataProvider(): array
    {
        return [
//            '0/50' => [0, 50],
            '10/100' => [10, 100],
            '1/10' => [1, 10],
        ];
    }
}
