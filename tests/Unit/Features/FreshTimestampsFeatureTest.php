<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Lapaliv\BulkUpsert\Features\FreshTimestampsFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;

final class FreshTimestampsFeatureTest extends UnitTestCase
{
    public function testWithTimestamps(): void
    {
        // arrange
        /** @var FreshTimestampsFeature $sut */
        $sut = $this->app->make(FreshTimestampsFeature::class);
        $model = new MySqlEntityWithAutoIncrement();

        // act
        $sut->handle($model);

        // assert
        self::assertNotNull($model->created_at);
        self::assertNotNull($model->updated_at);
    }

    public function testWithoutTimestamps(): void
    {
        // arrange
        /** @var FreshTimestampsFeature $sut */
        $sut = $this->app->make(FreshTimestampsFeature::class);
        $model = new MySqlEntityWithAutoIncrement();
        $model->timestamps = false;

        // act
        $sut->handle($model);

        // assert
        self::assertNull($model->created_at);
        self::assertNull($model->updated_at);
    }
}
