<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Lapaliv\BulkUpsert\Features\GetDateFieldsFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

final class GetDateFieldsFeatureTest extends TestCase
{
    public function test(): void
    {
        // assert
        $model = new PostgreSqlArticle();

        /** @var GetDateFieldsFeature $sut */
        $sut = $this->app->make(GetDateFieldsFeature::class);

        // act
        $result = $sut->handle($model);

        // assert
        self::assertEquals(
            [
                'date' => 'Y-m-d',
                'microseconds' => 'Y-m-d H:i:s.u',
                'created_at' => 'Y-m-d H:i:s',
                'updated_at' => 'Y-m-d H:i:s',
                'deleted_at' => 'Y-m-d H:i:s',
            ],
            $result
        );
    }
}
