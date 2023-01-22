<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Converters;

use Exception;
use Lapaliv\BulkUpsert\Converters\ArrayToCollectionConverter;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateEntityCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\Entity;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithoutAutoIncrement;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;

final class ArrayToCollectionConverterTest extends UnitTestCase
{
    private GenerateEntityCollectionTestFeature $generateEntityCollectionTestFeature;

    /**
     * @param string $model
     * @return void
     * @throws Exception
     * @dataProvider dataProvider
     */
    public function test(string $model): void
    {
        // arrange
        /** @var ArrayToCollectionConverter $sut */
        $sut = $this->app->make(ArrayToCollectionConverter::class);
        /** @var Entity $eloquent */
        $eloquent = new $model();
        $rows = [
            ...$this->generateEntityCollectionTestFeature->handle($model, 1),
            ...$this->generateEntityCollectionTestFeature->handle($model, 1)->toArray(),
        ];

        // act
        $result = $sut->handle($eloquent, $rows);

        // assert
        self::assertInstanceOf(get_class($eloquent->newCollection()), $result);
        $result->each(
            static function (mixed $item) use ($model): void {
                self::assertInstanceOf($model, $item);
            }
        );
    }

    /**
     * @return string[][]
     */
    public function dataProvider(): array
    {
        return [
            [MySqlEntityWithAutoIncrement::class],
            [MySqlEntityWithoutAutoIncrement::class],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateEntityCollectionTestFeature = $this->app->make(GenerateEntityCollectionTestFeature::class);
    }
}
