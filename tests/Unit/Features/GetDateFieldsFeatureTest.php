<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Lapaliv\BulkUpsert\Features\GetDateFieldsFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;

final class GetDateFieldsFeatureTest extends UnitTestCase
{
    public function test(): void
    {
        // assert
        $model = new MySqlEntityWithAutoIncrement();

        /** @var GetDateFieldsFeature $sut */
        $sut = $this->app->make(GetDateFieldsFeature::class);

        // act
        $result = $sut->handle($model);

        // assert
        self::assertEquals(
            [
                'date' => 'Y-m-d',
                'nullable_date' => 'Y-m-d',
                'custom_datetime' => $model::CUSTOM_DATE_FORMAT,
                'nullable_custom_datetime' => $model::CUSTOM_DATE_FORMAT,
                'microseconds' => $model::MICROSECONDS_FORMAT,
                'nullable_microseconds' => $model::MICROSECONDS_FORMAT,
                'created_at' => 'Y-m-d H:i:s',
                'updated_at' => 'Y-m-d H:i:s',
            ],
            $result
        );
    }
}
