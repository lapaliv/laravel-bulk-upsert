<?php

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Exceptions\BulkModelIsUndefined;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;
use stdClass;

final class GetBulkModelFeatureTest extends UnitTestCase
{
    /**
     * @param string|BulkModel $model
     * @return void
     * @dataProvider correctDataProvider
     * @noinspection UnnecessaryAssertionInspection
     */
    public function testCorrect(string|BulkModel $model): void
    {
        // arrange
        /** @var GetBulkModelFeature $sut */
        $sut = $this->app->make(GetBulkModelFeature::class);

        // act
        $result = $sut->handle($model);

        // assert
        self::assertInstanceOf(BulkModel::class, $result);
    }

    /**
     * @param mixed $model
     * @return void
     * @dataProvider throwBulkModelIsUndefinedDataProvider
     */
    public function testThrowBulkModelIsUndefined(mixed $model): void
    {
        // arrange
        /** @var GetBulkModelFeature $sut */
        $sut = $this->app->make(GetBulkModelFeature::class);

        // act/assert
        $this->assertThrows(
            fn () => $sut->handle($model),
            BulkModelIsUndefined::class
        );
    }

    /**
     * @return array[]
     */
    public function correctDataProvider(): array
    {
        return [
            'string' => [MySqlEntityWithAutoIncrement::class],
            'eloquent' => [new MySqlEntityWithAutoIncrement()],
        ];
    }

    /**
     * @return string[][]
     */
    public function throwBulkModelIsUndefinedDataProvider(): array
    {
        return [
            'random string' => ['\Abcd'],
            'stdClass' => [stdClass::class],
        ];
    }
}
