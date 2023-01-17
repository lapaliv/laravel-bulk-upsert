<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Insert;

use Faker\Factory;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

class InsertDuplicatesWithIgnoringTest extends TestCase
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
            'existingUsers' => $existingUsers,
            'existingEmails' => $existingEmails,
            'collection' => $collection,
            'sut' => $sut,
        ] = $this->arrange($model);

        // act
        $sut->insertOrIgnore($model, ['email'], $collection);

        // assert
        $existingUsers->each(
            function (User $user): void {
                $this->assertDatabaseHas(
                    $user->getTable(),
                    $user->toArray(),
                    $user->getConnectionName(),
                );
            }
        );

        $collection
            ->filter(
                fn (User $user) => $existingEmails->contains($user->email) === false
            )
            ->each(
                function (User $user): void {
                    $this->assertDatabaseHas(
                        $user->getTable(),
                        $user->toArray(),
                        $user->getConnectionName(),
                    );
                }
            );

        $collection
            ->filter(
                fn (User $user) => $existingEmails->contains($user->email)
            )
            ->each(
                function (User $user): void {
                    $this->assertDatabaseMissing(
                        $user->getTable(),
                        $user->toArray(),
                        $user->getConnectionName(),
                    );
                }
            );
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
     *     existingEmails: \Illuminate\Database\Eloquent\Collection,
     *     collection: \Illuminate\Database\Eloquent\Collection,
     *     sut: \Lapaliv\BulkUpsert\BulkInsert
     * }
     */
    private function arrange(string $model): array
    {
        $faker = Factory::create();
        $generateUserCollectionFeature = new GenerateUserCollectionFeature($model);
        $existingUsers = $generateUserCollectionFeature->handle(
            self::NUMBER_OF_EXISTING_ROWS
        );

        // creating the collection with different not unique values in the existing rows
        $collection = $generateUserCollectionFeature->handle(
            self::NUMBER_OF_NEW_ROWS
        );

        $existingUsers->each(
            static function (User $user) use ($collection, $faker): void {
                $clone = clone $user;
                $clone->name = $faker->name();
                $clone->phone = $faker->phoneNumber();

                $collection->push($clone);
            }
        );

        return [
            'existingUsers' => $existingUsers->each(
                fn (User $user) => $user->save()
            ),
            'existingEmails' => $existingUsers->pluck('email'),
            'collection' => $collection,
            'sut' => $this->app->make(BulkInsert::class),
        ];
    }
}
