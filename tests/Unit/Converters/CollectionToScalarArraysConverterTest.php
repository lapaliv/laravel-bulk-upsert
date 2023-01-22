<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Converters;

use Exception;
use Lapaliv\BulkUpsert\Converters\CollectionToScalarArraysConverter;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

final class CollectionToScalarArraysConverterTest extends TestCase
{
    private GenerateUserCollectionTestFeature $generateUserCollectionFeature;

    /**
     * @return void
     * @throws Exception
     */
    public function test(): void
    {
        // arrange
        /** @var CollectionToScalarArraysConverter $sut */
        $sut = $this->app->make(CollectionToScalarArraysConverter::class);
        $columns = ['email', 'name', 'phone', 'date', 'microseconds'];
        $rows = [
            ...$this->generateUserCollectionFeature->handle(MySqlUser::class, 1, $columns),
            ...$this->generateUserCollectionFeature->handle(MySqlUser::class, 1, $columns)->toArray(),
        ];

        // act
        $result = $sut->handle($rows);

        // assert
        self::assertIsArray($result);
        foreach ($result as $item) {
            self::assertIsArray($item);

            foreach ($item as $value) {
                self::assertIsScalar($value);
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateUserCollectionFeature = $this->app->make(GenerateUserCollectionTestFeature::class);
    }
}
