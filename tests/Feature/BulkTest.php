<?php

namespace Lapaliv\BulkUpsert\Tests\Feature;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Bulk;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Support\Callback;
use Lapaliv\BulkUpsert\Tests\FeatureTestCase;
use Mockery;

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

    /**
     * @param string $method
     * @return void
     * @dataProvider updateOnlyDataProvider
     */
    public function testUpdateOnly(string $method): void
    {
        // arrange
        $createdUsers = MySqlUser::factory()
            ->count(2)
            ->create([
                'created_at' => Carbon::now()->subYear(),
                'updated_at' => Carbon::now()->subMonths(3),
            ]);
        $updatingUsers = MySqlUser::factory()
            ->count(2)
            ->make()
            ->each(
                function (MySqlUser $user, int $index) use ($createdUsers): void {
                    $user->email = $createdUsers->get($index)->email;
                    $user->id = $createdUsers->get($index)->id;
                    $user->created_at = $createdUsers->get($index)->created_at;
                    $user->updated_at = $createdUsers->get($index)->updated_at;
                }
            );
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class)
            ->updateOnly(['name']);

        // act
        $sut->{$method}($updatingUsers);

        // assert
        $updatingUsers->each(
            fn (MySqlUser $user, int $index) => $this->assertDatabaseHas(
                $user->getTable(),
                [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'created_at' => $user->created_at->toDateTimeString(),
                    'updated_at' => $user->updated_at->toDateTimeString(),
                ],
                $user->getConnectionName()
            )
        );
    }

    /**
     * @param string $method
     * @return void
     * @dataProvider updateOnlyDataProvider
     */
    public function testUpdateOnlyBeforeDeleting(string $method): void
    {
        // arrange
        $createdUsers = MySqlUser::factory()
            ->count(2)
            ->create([
                'created_at' => Carbon::now()->subYear(),
                'updated_at' => Carbon::now()->subMonths(3),
            ]);
        $updatingUsers = MySqlUser::factory()
            ->count(2)
            ->make([
                'deleted_at' => Carbon::now()->subDay(),
            ])
            ->each(
                function (MySqlUser $user, int $index) use ($createdUsers): void {
                    $user->email = $createdUsers->get($index)->email;
                    $user->id = $createdUsers->get($index)->id;
                    $user->created_at = $createdUsers->get($index)->created_at;
                    $user->updated_at = $createdUsers->get($index)->updated_at;
                }
            );
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class)
            ->updateOnly(['name'])
            ->updateOnlyBeforeDeleting(['phone']);

        // act
        $sut->{$method}($updatingUsers);

        // assert
        $updatingUsers->each(
            function (MySqlUser $user): void {
                $this->assertDatabaseHas(
                    $user->getTable(),
                    [
                        'id' => $user->id,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'created_at' => $user->created_at->toDateTimeString(),
                        'updated_at' => $user->updated_at->toDateTimeString(),
                        'deleted_at' => $user->deleted_at->toDateTimeString(),
                    ],
                    $user->getConnectionName()
                );

                $this->assertDatabaseMissing(
                    $user->getTable(),
                    [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
                    $user->getConnectionName()
                );
            }
        );
    }

    /**
     * @param string $method
     * @return void
     * @dataProvider updateOnlyDataProvider
     */
    public function testUpdateOnlyBeforeRestoring(string $method): void
    {
        // arrange
        $createdUsers = MySqlUser::factory()
            ->count(2)
            ->create([
                'created_at' => Carbon::now()->subYear(),
                'updated_at' => Carbon::now()->subMonths(3),
                'deleted_at' => Carbon::now()->subDay(),
            ]);
        $updatingUsers = MySqlUser::factory()
            ->count(2)
            ->make(['deleted_at' => null])
            ->each(
                function (MySqlUser $user, int $index) use ($createdUsers): void {
                    $user->email = $createdUsers->get($index)->email;
                    $user->id = $createdUsers->get($index)->id;
                    $user->created_at = $createdUsers->get($index)->created_at;
                    $user->updated_at = $createdUsers->get($index)->updated_at;
                }
            );
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->identifyBy(['email'])
            ->model(MySqlUser::class)
            ->updateOnly(['name'])
            ->updateOnlyBeforeRestoring(['phone']);

        // act
        $sut->{$method}($updatingUsers);

        // assert
        $updatingUsers->each(
            function (MySqlUser $user): void {
                $this->assertDatabaseHas(
                    $user->getTable(),
                    [
                        'id' => $user->id,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'created_at' => $user->created_at->toDateTimeString(),
                        'updated_at' => $user->updated_at->toDateTimeString(),
                        'deleted_at' => null,
                    ],
                    $user->getConnectionName()
                );

                $this->assertDatabaseMissing(
                    $user->getTable(),
                    [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
                    $user->getConnectionName()
                );
            }
        );
    }

    public function testInsertWithFewIdentifiers(): void
    {
        // arrange
        $usersWithCountries = MySqlUser::factory()
            ->count(2)
            ->make(['phone' => null])
            ->keyBy('country');
        $usersWithPhones = MySqlUser::factory()
            ->count(2)
            ->make(['country' => null])
            ->keyBy('phone');
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->model(MySqlUser::class)
            ->identifyBy(['country'])
            ->orIdentifyBy(['phone']);

        // act
        $sut->insertOrAccumulate($usersWithCountries)
            ->insert($usersWithPhones);

        // assert
        $usersWithCountries->each(
            fn (MySqlUser $user) => $this->assertDatabaseHas(
                $user->getTable(),
                [
                    'email' => $user->email,
                    'country' => $user->country,
                    'phone' => null,
                ],
                $user->getConnectionName()
            )
        );
        $usersWithPhones->each(
            fn (MySqlUser $user) => $this->assertDatabaseHas(
                $user->getTable(),
                [
                    'email' => $user->email,
                    'country' => null,
                    'phone' => $user->phone,
                ],
                $user->getConnectionName()
            )
        );
    }

    /**
     * @return void
     */
    public function testUpdateWithFewIdentifiers(): void
    {
        // arrange
        $createdUsers = MySqlUser::factory()->count(4)->create();
        $usersWithCountries = MySqlUser::factory()
            ->count(2)
            ->make()
            ->each(function (MySqlUser $user, int $index) use ($createdUsers): void {
                $user->country = $createdUsers->get($index)->country;
                $user->id = $createdUsers->get($index)->id;
            })
            ->keyBy('country');
        $usersWithPhones = MySqlUser::factory()
            ->count(2)
            ->make()
            ->each(function (MySqlUser $user, int $index) use ($createdUsers): void {
                $user->phone = $createdUsers->get($index + 2)->phone;
                $user->id = $createdUsers->get($index + 2)->id;
            })
            ->keyBy('phone');
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->model(MySqlUser::class)
            ->identifyBy(['country'])
            ->orIdentifyBy(['phone']);

        // act
        $sut->updateOrAccumulate($usersWithCountries)
            ->update($usersWithPhones);

        // assert
        $usersWithCountries->each(
            fn (MySqlUser $user) => $this->assertDatabaseHas(
                $user->getTable(),
                [
                    'id' => $user->id,
                    'country' => $user->country,
                ],
                $user->getConnectionName()
            )
        );
        $usersWithPhones->each(
            fn (MySqlUser $user) => $this->assertDatabaseHas(
                $user->getTable(),
                [
                    'id' => $user->id,
                    'phone' => $user->phone,
                ],
                $user->getConnectionName()
            )
        );
    }

    public function testInsertCallbacks(): void
    {
        // arrange
        $users = collect([
            MySqlUser::factory()->make(),
            MySqlUser::factory()->make([
                'deleted_at' => Carbon::now(),
            ]),
        ]);
        $creatingSpy = Mockery::spy(Callback::class);
        $createdSpy = Mockery::spy(Callback::class);
        $updatingSpy = Mockery::spy(Callback::class);
        $updatedSpy = Mockery::spy(Callback::class);
        $deletingSpy = Mockery::spy(Callback::class);
        $deletedSpy = Mockery::spy(Callback::class);
        $restoringSpy = Mockery::spy(Callback::class);
        $restoredSpy = Mockery::spy(Callback::class);
        $savingSpy = Mockery::spy(Callback::class);
        $savedSpy = Mockery::spy(Callback::class);
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->model(MySqlUser::class)
            ->identifyBy(['email'])
            ->beforeCreating($creatingSpy)
            ->afterCreating($createdSpy)
            ->beforeDeleting($deletingSpy)
            ->afterDeleting($deletedSpy)
            ->beforeSaving($savingSpy)
            ->afterSaving($savedSpy)
            ->beforeRestoring($restoringSpy)
            ->afterRestoring($restoredSpy)
            ->beforeUpdating($updatingSpy)
            ->afterUpdating($updatedSpy);

        // act
        $sut->insert($users);

        // assert
        $creatingSpy->shouldHaveReceived('__invoke')->times(1);
        $createdSpy->shouldHaveReceived('__invoke')->times(1);
        $deletingSpy->shouldHaveReceived('__invoke')->times(1);
        $deletedSpy->shouldHaveReceived('__invoke')->times(1);
        $savingSpy->shouldNotHaveReceived('__invoke');
        $savedSpy->shouldHaveReceived('__invoke')->times(1);
        $updatingSpy->shouldNotHaveReceived('__invoke');
        $updatedSpy->shouldNotHaveReceived('__invoke');
        $restoringSpy->shouldNotHaveReceived('__invoke');
        $restoredSpy->shouldNotHaveReceived('__invoke');
    }

    public function testUpdateCallbacks(): void
    {
        // arrange
        $users = collect([
            MySqlUser::factory()->make([
                'email' => MySqlUser::factory()->create()->email,
            ]),
            MySqlUser::factory()->make([
                'email' => MySqlUser::factory()->create()->email,
                'deleted_at' => Carbon::now(),
            ]),
            MySqlUser::factory()->make([
                'email' => MySqlUser::factory()->create(['deleted_at' => Carbon::now()])->email,
                'deleted_at' => null,
            ]),
        ]);
        $creatingSpy = Mockery::spy(Callback::class);
        $createdSpy = Mockery::spy(Callback::class);
        $updatingSpy = Mockery::spy(Callback::class);
        $updatedSpy = Mockery::spy(Callback::class);
        $deletingSpy = Mockery::spy(Callback::class);
        $deletedSpy = Mockery::spy(Callback::class);
        $restoringSpy = Mockery::spy(Callback::class);
        $restoredSpy = Mockery::spy(Callback::class);
        $savingSpy = Mockery::spy(Callback::class);
        $savedSpy = Mockery::spy(Callback::class);
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->model(MySqlUser::class)
            ->identifyBy(['email'])
            ->beforeCreating($creatingSpy)
            ->afterCreating($createdSpy)
            ->beforeDeleting($deletingSpy)
            ->afterDeleting($deletedSpy)
            ->beforeSaving($savingSpy)
            ->afterSaving($savedSpy)
            ->beforeRestoring($restoringSpy)
            ->afterRestoring($restoredSpy)
            ->beforeUpdating($updatingSpy)
            ->afterUpdating($updatedSpy);

        // act
        $sut->update($users);

        // assert
        $creatingSpy->shouldNotHaveReceived('__invoke');
        $createdSpy->shouldNotHaveReceived('__invoke');
        $deletingSpy->shouldHaveReceived('__invoke')->times(1);
        $deletedSpy->shouldHaveReceived('__invoke')->times(1);
        $savingSpy->shouldHaveReceived('__invoke')->times(1);
        $savedSpy->shouldHaveReceived('__invoke')->times(1);
        $updatingSpy->shouldHaveReceived('__invoke')->times(1);
        $updatedSpy->shouldHaveReceived('__invoke')->times(1);
        $restoringSpy->shouldHaveReceived('__invoke')->times(1);
        $restoredSpy->shouldHaveReceived('__invoke')->times(1);
    }

    public function testUpsertCallbacks(): void
    {
        // arrange
        $users = collect([
            MySqlUser::factory()->make(),
            MySqlUser::factory()->make([
                'email' => MySqlUser::factory()->create()->email,
                'deleted_at' => Carbon::now(),
            ]),
            MySqlUser::factory()->make([
                'email' => MySqlUser::factory()->create(['deleted_at' => Carbon::now()])->email,
                'deleted_at' => null,
            ]),
        ]);
        $creatingSpy = Mockery::spy(Callback::class);
        $createdSpy = Mockery::spy(Callback::class);
        $updatingSpy = Mockery::spy(Callback::class);
        $updatedSpy = Mockery::spy(Callback::class);
        $deletingSpy = Mockery::spy(Callback::class);
        $deletedSpy = Mockery::spy(Callback::class);
        $restoringSpy = Mockery::spy(Callback::class);
        $restoredSpy = Mockery::spy(Callback::class);
        $savingSpy = Mockery::spy(Callback::class);
        $savedSpy = Mockery::spy(Callback::class);
        /** @var Bulk $sut */
        $sut = $this->app->make(Bulk::class);
        $sut->model(MySqlUser::class)
            ->identifyBy(['email'])
            ->beforeCreating($creatingSpy)
            ->afterCreating($createdSpy)
            ->beforeDeleting($deletingSpy)
            ->afterDeleting($deletedSpy)
            ->beforeSaving($savingSpy)
            ->afterSaving($savedSpy)
            ->beforeRestoring($restoringSpy)
            ->afterRestoring($restoredSpy)
            ->beforeUpdating($updatingSpy)
            ->afterUpdating($updatedSpy);

        // act
        $sut->upsert($users);

        // assert
        $creatingSpy->shouldHaveReceived('__invoke')->times(1);
        $createdSpy->shouldHaveReceived('__invoke')->times(1);
        $deletingSpy->shouldHaveReceived('__invoke')->times(1);
        $deletedSpy->shouldHaveReceived('__invoke')->times(1);
        $savingSpy->shouldHaveReceived('__invoke')->times(1);
        $savedSpy->shouldHaveReceived('__invoke')->times(2);
        $updatingSpy->shouldHaveReceived('__invoke')->times(1);
        $updatedSpy->shouldHaveReceived('__invoke')->times(1);
        $restoringSpy->shouldHaveReceived('__invoke')->times(1);
        $restoredSpy->shouldHaveReceived('__invoke')->times(1);
    }

    public function updateOnlyDataProvider(): array
    {
        return [
            ['update'],
            ['upsert'],
        ];
    }
}
