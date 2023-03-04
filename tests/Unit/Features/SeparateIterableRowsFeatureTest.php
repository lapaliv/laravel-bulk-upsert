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
     * @dataProvider dataProviderWithLimits
     */
    public function testWithLimits(int $chunkSize, int $limit): void
    {
        // arrange
        $data = array_fill(0, $limit, null);

        /** @var SeparateIterableRowsFeature $sut */
        $sut = $this->app->make(SeparateIterableRowsFeature::class);

        // act
        $generator = $sut->handle($chunkSize, $data);

        // assert
        $numberOfChunks = 0;
        foreach ($generator as $chunk) {
            self::assertLessThanOrEqual($chunkSize, count($chunk));
            $numberOfChunks ++;
        }

        self::assertEquals(ceil($limit / $chunkSize), $numberOfChunks);
    }

    /**
     * @param int $limit
     * @return void
     * @dataProvider dataProviderWithoutLimits
     */
    public function testWithoutLimits(int $limit): void
    {
        // arrange
        $data = array_fill(0, $limit, null);

        /** @var SeparateIterableRowsFeature $sut */
        $sut = $this->app->make(SeparateIterableRowsFeature::class);

        // act
        $generator = $sut->handle(0, $data);

        // assert
        $numberOfChunks = 0;
        foreach ($generator as $chunk) {
            self::assertLessThanOrEqual($limit, count($chunk));
            $numberOfChunks ++;
        }

        self::assertEquals(1, $numberOfChunks);
    }

    /**
     * @return int[][]
     */
    public function dataProviderWithLimits(): array
    {
        return [
            '10/100' => [10, 100],
            '1/10' => [1, 10],
        ];
    }

    /**
     * @return int[][]
     */
    public function dataProviderWithoutLimits(): array
    {
        return [
            '50' => [50],
            '100' => [100],
        ];
    }
}
