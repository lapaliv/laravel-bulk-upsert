<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Insert;

use Illuminate\Database\QueryException;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

class InsertDuplicatesWithoutIgnoringTest extends TestCase
{
    private const NUMBER_OF_EXISTING_ROWS = 2;
    private const NUMBER_OF_NEW_ROWS = 3;

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

        // assert
        $this->expectException(QueryException::class);

        // act
        $sut->insert($model, ['email'], $collection);
    }

    public function data(): array
    {
        return [
            [MySqlUser::class],
            [PostgreSqlUser::class],
        ];
    }

    /**
     * @param string $model
     * @return array{
     *     existingUsers: \Illuminate\Database\Eloquent\Collection,
     *     collection: \Illuminate\Database\Eloquent\Collection,
     *     sut: \Lapaliv\BulkUpsert\BulkInsert
     * }
     */
    private function arrange(string $model): array
    {
        $generateUserCollectionFeature = new GenerateUserCollectionFeature($model);
        $existingUsers = $generateUserCollectionFeature
            ->handle(
                self::NUMBER_OF_EXISTING_ROWS
            )
            ->each(
                fn (User $user) => $user->save()
            );

        // creating the collection with different not unique values in the existing rows
        $collection = $generateUserCollectionFeature->handle(
            self::NUMBER_OF_NEW_ROWS
        );

        $collection->push(...$existingUsers);

        return [
            'collection' => $collection,
            'sut' => $this->app->make(BulkInsert::class),
        ];
    }
}
