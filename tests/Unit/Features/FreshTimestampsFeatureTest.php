<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Lapaliv\BulkUpsert\Features\FreshTimestampsFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

final class FreshTimestampsFeatureTest extends TestCase
{
    public function testWithTimestamps(): void
    {
        // arrange
        /** @var FreshTimestampsFeature $sut */
        $sut = $this->app->make(FreshTimestampsFeature::class);
        $model = new MySqlUser();

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
        $model = new MySqlUser();
        $model->timestamps = false;

        // act
        $sut->handle($model);

        // assert
        self::assertNull($model->created_at);
        self::assertNull($model->updated_at);
    }
}
