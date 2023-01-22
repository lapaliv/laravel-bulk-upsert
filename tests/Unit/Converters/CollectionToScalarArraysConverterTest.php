<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Converters;

use Exception;
use Lapaliv\BulkUpsert\Converters\CollectionToScalarArraysConverter;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateEntityCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;

final class CollectionToScalarArraysConverterTest extends UnitTestCase
{
    private GenerateEntityCollectionTestFeature $generateEntityCollectionTestFeature;

    /**
     * @return void
     * @throws Exception
     */
    public function test(): void
    {
        // arrange
        /** @var CollectionToScalarArraysConverter $sut */
        $sut = $this->app->make(CollectionToScalarArraysConverter::class);
        $rows = [
            ...$this->generateEntityCollectionTestFeature
                ->handle(MySqlEntityWithAutoIncrement::class, 1, ['string', 'integer', 'decimal', 'json']),
            ...$this->generateEntityCollectionTestFeature
                ->handle(MySqlEntityWithAutoIncrement::class, 1, ['string', 'integer', 'decimal', 'json'])
                ->toArray(),
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

        $this->generateEntityCollectionTestFeature = $this->app->make(GenerateEntityCollectionTestFeature::class);
    }
}
