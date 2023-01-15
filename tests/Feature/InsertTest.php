<?php

namespace Lapaliv\BulkUpsert\Tests\Feature;

use Carbon\Carbon;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\Collections\UserCollection;
use Lapaliv\BulkUpsert\Tests\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\Models\PostgresUser;
use Lapaliv\BulkUpsert\Tests\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

class InsertTest extends TestCase
{
    private Generator $faker;

    public function testChunkSize(): void
    {
        // arrange
        $numberOfChunks = 0;
        $numberOfUsers = 5;
        $chunkSize = 1;

        $generateUserCollectionFeature = new GenerateUserCollectionFeature(MysqlUser::class);
        $collection = $generateUserCollectionFeature->handle($numberOfUsers);
        $sut = $this->app->make(BulkInsert::class)
            ->chunk(
                $chunkSize,
                function (UserCollection $chunk) use ($chunkSize, &$numberOfChunks): void {
                    $this->assertCount($chunkSize, $chunk);

                    $numberOfChunks++;
                }
            );

        // act
        $sut->insert(MysqlUser::class, ['email'], $collection);

        // assert
        $this->assertEquals(
            ceil($numberOfUsers / $chunkSize),
            $numberOfChunks
        );
    }

    /**
     * @dataProvider models
     * @param string $model
     * @return void
     * @throws Exception
     */
    public function testSuccess1(string $model): void
    {
        // arrange
        $numberOfUsers = 5;
        $collection = (new GenerateUserCollectionFeature($model))->handle($numberOfUsers);
        $sut = $this->app->make(BulkInsert::class);

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

    /**
     * @dataProvider models
     * @param string $model
     * @return void
     */
    public function testDateFields(string $model): void
    {
        // arrange
        $numberOfUsers = 2;
        $collection = new UserCollection();
        $sut = $this->app->make(BulkInsert::class);

        for ($i = 0; $i < $numberOfUsers; $i++) {
            $collection->push(
                new $model([
                    'email' => $this->faker->unique()->email(),
                    'name' => $this->faker->name(),
                    'date' => Carbon::parse($this->faker->dateTime()),
                ]),
            );
        }

        // act
        $sut->insert($model, ['email'], $collection);

        // assert
        $collection->each(
            function (User $expectedUser): void {
                /** @var User $actualUser */
                $actualUser = $expectedUser->newQuery()
                    ->where('email', $expectedUser->email)
                    ->first();

                self::assertEquals(
                    $expectedUser->date->startOfDay()->format('Y-m-d H:i:s.u'),
                    $actualUser->date->format('Y-m-d H:i:s.u')
                );
            }
        );
    }

    /**
     * @dataProvider models
     * @param string $model
     * @return void
     * @throws Exception
     */
    public function testInsertDuplicatesWithIgnoring(string $model): void
    {
        $numberOfExistingRows = 2;
        $numberOfNewRows = 3;

        $generateUserCollectionFeature = new GenerateUserCollectionFeature($model);
        $existingUsers = $generateUserCollectionFeature->handle($numberOfExistingRows);
        // creating the collection with different not unique values in the existing rows
        $collection = $generateUserCollectionFeature->handle($numberOfNewRows);
        $sut = $this->app->make(BulkInsert::class);

        $existingUsers->each(
            function (User $user) use ($collection): void {
                $clone = clone $user;
                $clone->name = $this->faker->name();
                $clone->phone = $this->faker->phoneNumber();

                $collection->push($clone);
            }
        );

        $existingEmails = $existingUsers->pluck('email');

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
                fn(User $user) => $existingEmails->contains($user->email) === false
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
                fn(User $user) => $existingEmails->contains($user->email)
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    protected function models(): array
    {
        return [
            [MysqlUser::class],
            [PostgresUser::class],
        ];
    }
}
