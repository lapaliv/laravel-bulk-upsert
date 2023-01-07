<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Insert;

use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\Models\PostgresUser;
use Lapaliv\BulkUpsert\Tests\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

final class CommonTest extends TestCase
{
    private const NUMBER_OF_USERS = 5;

    /**
     * @dataProvider data
     * @param string $model
     * @return void
     */
    public function test(string $model): void
    {
        [
            'collection' => $collection,
            'sut' => $sut,
        ] = $this->arrange($model);

        // act
        $sut->insert($model, ['email'], $collection);

        // assert
        $collection->map(
            function (User $user): void {
                $this->assertDatabaseHas(
                    $user->getTable(),
                    $user->getAttributes(),
                    $user->getConnectionName()
                );
            }
        );
    }

    public function data(): array
    {
        return [
            [MysqlUser::class],
            [PostgresUser::class],
        ];
    }

    /**
     * @param string $model
     * @return array{
     *     collection: \Illuminate\Database\Eloquent\Collection,
     *     sut: \Lapaliv\BulkUpsert\BulkInsert
     * }
     */
    protected function arrange(string $model): array
    {
        $generateUserCollectionFeature = new GenerateUserCollectionFeature($model);

        return [
            'collection' => $generateUserCollectionFeature->handle(
                self::NUMBER_OF_USERS
            ),
            'sut' => $this->app->make(BulkInsert::class),
        ];
    }
}
