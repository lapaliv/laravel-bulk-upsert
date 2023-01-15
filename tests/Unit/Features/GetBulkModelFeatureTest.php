<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Exceptions\BulkModelIsUndefined;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;
use stdClass;

class GetBulkModelFeatureTest extends TestCase
{
    /**
     * @param string|BulkModel $model
     * @return void
     * @dataProvider correctDataProvider
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
            fn() => $sut->handle($model),
            BulkModelIsUndefined::class
        );
    }

    protected function correctDataProvider(): array
    {
        return [
            'string' => [MysqlUser::class],
            'eloquent' => [new MysqlUser()],
        ];
    }

    protected function throwBulkModelIsUndefinedDataProvider(): array
    {
        return [
            'random string' => ['\Abcd'],
            'stdClass' => [stdClass::class],
        ];
    }
}
