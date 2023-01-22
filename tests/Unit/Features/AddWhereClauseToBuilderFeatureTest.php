<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Exception;
use Lapaliv\BulkUpsert\Features\AddWhereClauseToBuilderFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

final class AddWhereClauseToBuilderFeatureTest extends TestCase
{
    private GenerateUserCollectionTestFeature $generateUserCollectionFeature;

    /**
     * @return void
     * @throws Exception
     */
    public function testOneField(): void
    {
        // arrange
        $builder = MySqlUser::query();
        $uniqueAttributes = ['email'];
        $users = $this->generateUserCollectionFeature->handle(MySqlUser::class, 5, $uniqueAttributes);
        /** @var AddWhereClauseToBuilderFeature $sut */
        $sut = $this->app->make(AddWhereClauseToBuilderFeature::class);

        // act
        $sut->handle($builder, $uniqueAttributes, $users);

        // assert
        self::assertStringContainsString(
            sprintf(
                '`email` in (%s)',
                implode(', ', array_fill(0, $users->count(), '?'))
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
        $builder = MySqlUser::query();
        $uniqueAttributes = ['email', 'name'];
        $users = $this->generateUserCollectionFeature->handle(MySqlUser::class, 5, $uniqueAttributes);
        /** @var AddWhereClauseToBuilderFeature $sut */
        $sut = $this->app->make(AddWhereClauseToBuilderFeature::class);

        // act
        $sut->handle($builder, $uniqueAttributes, $users);

        // assert
        $condition = implode(
            ' or ',
            array_fill(0, $users->count(), '(`email` = ? and `name` = ?)')
        );

        self::assertStringContainsString(
            sprintf('(%s)', $condition),
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
        $builder = MySqlUser::query();
        $uniqueAttributes = ['email', 'name', 'phone'];
        $users = $this->generateUserCollectionFeature->handle(MySqlUser::class, 5, $uniqueAttributes);
        /** @var AddWhereClauseToBuilderFeature $sut */
        $sut = $this->app->make(AddWhereClauseToBuilderFeature::class);

        // act
        $sut->handle($builder, $uniqueAttributes, $users);

        // assert
        $condition = implode(
            ' or ',
            array_fill(0, $users->count(), '(`email` = ? and ((`name` = ? and `phone` = ?)))')
        );

        self::assertStringContainsString(
            sprintf('(%s)', $condition),
            $builder->toSql()
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateUserCollectionFeature = $this->app->make(GenerateUserCollectionTestFeature::class);
    }
}
