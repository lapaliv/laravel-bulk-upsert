<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Exception;
use Lapaliv\BulkUpsert\Features\AddWhereClauseToBuilderFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateEntityCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;

final class AddWhereClauseToBuilderFeatureTest extends UnitTestCase
{
    private GenerateEntityCollectionTestFeature $generateEntityCollectionTestFeature;

    /**
     * @return void
     * @throws Exception
     */
    public function testOneField(): void
    {
        // arrange
        $model = MySqlEntityWithAutoIncrement::class;
        $builder = $model::query();
        $uniqueAttributes = ['uuid'];
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 5, $uniqueAttributes);
        /** @var AddWhereClauseToBuilderFeature $sut */
        $sut = $this->app->make(AddWhereClauseToBuilderFeature::class);

        // act
        $sut->handle($builder, $uniqueAttributes, $entities);

        // assert
        self::assertStringContainsString(
            sprintf(
                '`uuid` in (%s)',
                implode(', ', array_fill(0, $entities->count(), '?'))
            ),
            $builder->toSql()
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testTwoFields(): void
    {
        // assert
        $model = MySqlEntityWithAutoIncrement::class;
        $builder = $model::query();
        $uniqueAttributes = ['uuid', 'integer'];
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 5, $uniqueAttributes);
        /** @var AddWhereClauseToBuilderFeature $sut */
        $sut = $this->app->make(AddWhereClauseToBuilderFeature::class);

        // act
        $sut->handle($builder, $uniqueAttributes, $entities);

        // assert
        $condition = implode(
            ' or ',
            array_fill(0, $entities->count(), '(`uuid` = ? and `integer` = ?)')
        );

        self::assertStringContainsString(
            $condition,
            $builder->toSql()
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testThreeFields(): void
    {
        // assert
        $model = MySqlEntityWithAutoIncrement::class;
        $builder = $model::query();
        $uniqueAttributes = ['uuid', 'string', 'integer'];
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 5, $uniqueAttributes);
        /** @var AddWhereClauseToBuilderFeature $sut */
        $sut = $this->app->make(AddWhereClauseToBuilderFeature::class);

        // act
        $sut->handle($builder, $uniqueAttributes, $entities);

        // assert
        $condition = implode(
            ' or ',
            array_fill(0, $entities->count(), '(`uuid` = ? and ((`string` = ? and `integer` = ?)))')
        );

        self::assertStringContainsString(
            $condition,
            $builder->toSql()
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateEntityCollectionTestFeature = $this->app->make(GenerateEntityCollectionTestFeature::class);
    }
}
