<?php

namespace Lapaliv\BulkUpsert\Tests\Feature;

use Lapaliv\BulkUpsert\Bulk;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\FeatureTestCase;

class BulkTest extends FeatureTestCase
{
    public function testInsert(): void
    {
        // arrange
        $users = MySqlUser::factory()->count(2)->make();
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class);

        // act
        $sut->insert($users);

        // assert
        $users->each(
            fn (MySqlUser $user) => $this->assertDatabaseHas(MySqlUser::table(), [
                'name' => $user->name,
                'email' => $user->email,
            ], $user->getConnectionName())
        );
    }

    public function testInsertOrAccumulateWithBigChunkSize(): void
    {
        // arrange
        $users = MySqlUser::factory()->count(2)->make();
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class);

        // act
        $sut->insertOrAccumulate($users);

        // assert
        $users->each(
            fn (MySqlUser $user) => $this->assertDatabaseMissing(MySqlUser::table(), [
                'email' => $user->email,
            ], $user->getConnectionName())
        );
    }

    public function testInsertOrAccumulateWithSmallChunkSize(): void
    {
        // arrange
        $users = MySqlUser::factory()->count(2)->make();
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class)
            ->chunk(2)
            ->insertOrAccumulate($users->slice(0, 1));

        // act
        $sut->insertOrAccumulate($users->slice(1, 1));

        // assert
        $users->each(
            fn (MySqlUser $user) => $this->assertDatabaseHas(MySqlUser::table(), [
                'email' => $user->email,
            ], $user->getConnectionName())
        );
    }

    public function testInsertAndReturn(): void
    {
        // arrange
        $users = MySqlUser::factory()->count(2)
            ->make()
            ->keyBy('email');
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class)
            ->chunk(1);

        // act
        $result = $sut->insertAndReturn($users);

        // assert
        $this->assertCount(2, $result);
        $result->each(
            function (MySqlUser $user) use ($users): void {
                self::assertArrayHasKey($user->email, $users);
                $this->assertDatabaseHas(MySqlUser::table(), [
                    'name' => $user->name,
                    'email' => $user->email,
                ], $user->getConnectionName());
            }
        );
    }

    public function testUpdate(): void
    {
        // arrange
        $oldUsers = MySqlUser::factory()->count(2)->create();
        $newUsers = MySqlUser::factory()
            ->count($oldUsers->count())
            ->make()
            ->each(
                function (MySqlUser $user, int $index) use ($oldUsers): void {
                    $user->email = $oldUsers->get($index)->email;
                }
            );
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class);

        // act
        $sut->update($newUsers);

        // assert
        $newUsers->each(
            fn (MySqlUser $user) => $this->assertDatabaseHas(
                MySqlUser::table(),
                $user->only('name', 'email'),
                $user->getConnectionName()
            )
        );
    }

    public function testUpdateOrAccumulateWithBigChunkSize(): void
    {
        // arrange
        $oldUsers = MySqlUser::factory()->count(2)->create();
        $newUsers = MySqlUser::factory()
            ->count($oldUsers->count())
            ->make()
            ->each(
                function (MySqlUser $user, int $index) use ($oldUsers): void {
                    $user->email = $oldUsers->get($index)->email;
                }
            );
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class);

        // act
        $sut->updateOrAccumulate($newUsers);

        // assert
        $newUsers->each(
            fn (MySqlUser $user) => $this->assertDatabaseMissing(
                MySqlUser::table(),
                $user->only('name', 'email'),
                $user->getConnectionName()
            )
        );
    }

    public function testUpdateOrAccumulateWithSmallChunkSize(): void
    {
        // arrange
        $oldUsers = MySqlUser::factory()->count(2)->create();
        $newUsers = MySqlUser::factory()
            ->count($oldUsers->count())
            ->make()
            ->each(
                function (MySqlUser $user, int $index) use ($oldUsers): void {
                    $user->email = $oldUsers->get($index)->email;
                }
            );
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class)
            ->chunk(2)
            ->updateOrAccumulate($newUsers->slice(0, 1));

        // act
        $sut->updateOrAccumulate($newUsers->slice(1, 1));

        // assert
        $newUsers->each(
            fn (MySqlUser $user) => $this->assertDatabaseHas(
                MySqlUser::table(),
                $user->only('name', 'email'),
                $user->getConnectionName()
            )
        );
    }

    public function testUpdateAndReturn(): void
    {
        // arrange
        $oldUsers = MySqlUser::factory()->count(2)->create();
        $newUsers = MySqlUser::factory()
            ->count($oldUsers->count())
            ->make()
            ->each(
                function (MySqlUser $user, int $index) use ($oldUsers): void {
                    $user->email = $oldUsers->get($index)->email;
                }
            )
            ->keyBy('email');
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class);

        // act
        $result = $sut->updateAndReturn($newUsers);

        // assert
        self::assertCount($newUsers->count(), $result);
        $result->each(
            function (MySqlUser $user) use ($newUsers): void {
                self::assertArrayHasKey($user->email, $newUsers);
                self::assertEquals($newUsers->get($user->email)->name, $user->name);
                self::assertTrue($user->id > 0);
                self::assertDatabaseHas(MySqlUser::table(), [
                    'email' => $user->email,
                    'name' => $user->name,
                    'id' => $user->id,
                ], $user->getConnectionName());
            }
        );
    }

    public function testUpsert(): void
    {
        // arrange
        $createdOldUsers = MySqlUser::factory()->count(2)->create();
        $upsertingUsers = [
            ...MySqlUser::factory()
                ->count($createdOldUsers->count())
                ->make()
                ->each(
                    function (MySqlUser $user, int $index) use ($createdOldUsers): void {
                        $user->email = $createdOldUsers->get($index)->email;
                    }
                ),
            ...MySqlUser::factory()->count(2)->make(),
        ];
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class);

        // act
        $sut->upsert($upsertingUsers);

        // assert
        foreach ($upsertingUsers as $user) {
            $this->assertDatabaseHas(
                MySqlUser::table(),
                $user->only('name', 'email'),
                $user->getConnectionName()
            );
        }
    }

    public function testUpsertOrAccumulateWithBigChunkSize(): void
    {
        // arrange
        $createdOldUsers = MySqlUser::factory()->count(2)->create();
        $upsertingUsers = [
            ...MySqlUser::factory()
                ->count($createdOldUsers->count())
                ->make()
                ->each(
                    function (MySqlUser $user, int $index) use ($createdOldUsers): void {
                        $user->email = $createdOldUsers->get($index)->email;
                    }
                ),
            ...MySqlUser::factory()->count(2)->make(),
        ];
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class);

        // act
        $sut->upsertOrAccumulate($upsertingUsers);

        // assert
        foreach ($upsertingUsers as $user) {
            $this->assertDatabaseMissing(
                MySqlUser::table(),
                $user->only('name', 'email'),
                $user->getConnectionName()
            );
        }
    }

    public function testUpsertOrAccumulateWithSmallChunkSize(): void
    {
        // arrange
        $createdOldUsers = MySqlUser::factory()->count(2)->create();
        $upsertingUsers = [
            ...MySqlUser::factory()
                ->count($createdOldUsers->count())
                ->make()
                ->each(
                    function (MySqlUser $user, int $index) use ($createdOldUsers): void {
                        $user->email = $createdOldUsers->get($index)->email;
                    }
                ),
            ...MySqlUser::factory()->count(2)->make(),
        ];
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class)
            ->chunk(count($upsertingUsers))
            ->upsertOrAccumulate(array_slice($upsertingUsers, 0, 2));

        // act
        $sut->upsertOrAccumulate(array_slice($upsertingUsers, 2));

        // assert
        foreach ($upsertingUsers as $user) {
            $this->assertDatabaseHas(
                MySqlUser::table(),
                $user->only('name', 'email'),
                $user->getConnectionName()
            );
        }
    }

    public function testUpsertAndReturn(): void
    {
        // arrange
        $createdOldUsers = MySqlUser::factory()->count(2)->create();
        $upsertingUsers = collect([
            ...MySqlUser::factory()
                ->count($createdOldUsers->count())
                ->make()
                ->each(
                    function (MySqlUser $user, int $index) use ($createdOldUsers): void {
                        $user->email = $createdOldUsers->get($index)->email;
                    }
                ),
            ...MySqlUser::factory()->count(2)->make(),
        ])->keyBy('email');
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class);

        // act
        $result = $sut->upsertAndReturn($upsertingUsers);

        // assert
        self::assertCount($upsertingUsers->count(), $result);
        $result->each(
            function (MySqlUser $user) use ($upsertingUsers): void {
                self::assertArrayHasKey($user->email, $upsertingUsers);
                self::assertEquals($upsertingUsers->get($user->email)->name, $user->name);
                self::assertTrue($user->id > 0);
                $this->assertDatabaseHas(
                    MySqlUser::table(),
                    $user->only('id', 'name', 'email'),
                    $user->getConnectionName()
                );
            }
        );
    }
}
