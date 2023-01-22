<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Tests\App\Support\Callback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Mockery;
use Mockery\VerificationDirector;

final class SeparateIterableRowsFeatureTest extends TestCase
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
        $spy = Mockery::spy(Callback::class);

        /** @var SeparateIterableRowsFeature $sut */
        $sut = $this->app->make(SeparateIterableRowsFeature::class);

        // act
        $sut->handle($chunkSize, $data, $spy);

        // assert
        /** @var VerificationDirector $method */
        $method = $spy->shouldHaveReceived('__invoke');
        $times = $chunkSize === 0 ? 1 : (int)ceil($limit / $chunkSize);
        $method->times($times)
            ->withArgs(
                function (...$args) use ($chunkSize, $limit): bool {
                    self::assertCount(1, $args);
                    self::assertLessThanOrEqual(
                        $chunkSize === 0 ? $limit : $chunkSize,
                        count($args[0])
                    );

                    return true;
                }
            );
    }

    public function dataProvider(): array
    {
        return [
            '0/50' => [0, 50],
            '10/100' => [10, 100],
            '1/10' => [1, 10],
        ];
    }
}
