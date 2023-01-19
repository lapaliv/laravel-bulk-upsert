<?php

namespace Lapaliv\BulkUpsert\Tests\Feature;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Support\Callback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Mockery;
use Mockery\VerificationDirector;

class InsertTest extends TestCase
{
    private GenerateUserCollectionFeature $generateUserCollectionFeature;

    /**
     * @param string $model
     * @return void
     * @throws Exception
     * @dataProvider models
     */
    public function testSuccessful(string $model): void
    {
        // arrange
        $users = $this->generateUserCollectionFeature->handle($model, 3);
        /** @var BulkInsert $sut */
        $sut = $this->app->make(BulkInsert::class);

        // act
        $sut->insert($model, ['email'], $users);

        // assert
        $users->each(
            fn (User $user) => $this->assertDatabaseHas(
                $user->getTable(),
                $user->toArray(),
                $user->getConnectionName()
            )
        );
    }

    /**
     * @param string $model
     * @return void
     * @throws Exception
     * @dataProvider models
     */
    public function testTimestamps(string $model): void
    {
        // arrange
        $users = $this->generateUserCollectionFeature->handle($model, 3);
        /** @var BulkInsert $sut */
        $sut = $this->app->make(BulkInsert::class);

        // act
        $sut->insert($model, ['email'], $users);

        // assert
        $users->each(
            function (User $user) {
                $hasUser = $user->newQuery()
                    ->where('email', $user->email)
                    ->whereNotNull($user->getCreatedAtColumn())
                    ->whereNotNull($user->getUpdatedAtColumn())
                    ->whereNull($user->getDeletedAtColumn())
                    ->exists();

                self::assertTrue($hasUser);
            }
        );
    }

    /**
     * @param class-string<Model> $model
     * @return void
     * @throws Exception
     * @dataProvider models
     */
    public function testDispatchedEvents(string $model): void
    {
        // arrange
        $users = $this->generateUserCollectionFeature->handle($model, 3);
        /** @var BulkInsert $sut */
        $sut = $this->app->make(BulkInsert::class);
        $spies = [
            BulkEventEnum::CREATING => Mockery::spy(Callback::class),
            BulkEventEnum::SAVING => Mockery::spy(Callback::class),
            BulkEventEnum::CREATED => Mockery::spy(Callback::class),
            BulkEventEnum::SAVED => Mockery::spy(Callback::class),
        ];

        foreach ($spies as $event => $spy) {
            $model::{$event}($spy);
        }

        // act
        $sut->insert($model, ['email'], $users);

        // assert
        foreach ([BulkEventEnum::CREATING, BulkEventEnum::SAVING] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $spies[$event]->shouldHaveReceived('__invoke');
            $callback->times($users->count())
                ->withArgs(
                    function (...$args) use ($model): bool {
                        self::assertCount(1, $args);
                        /** @var User $user */
                        $user = $args[0];
                        self::assertInstanceOf($model, $user);
                        self::assertFalse($user->wasRecentlyCreated);
                        self::assertFalse($user->exists);

                        return true;
                    }
                );
        }
        foreach ([BulkEventEnum::CREATED, BulkEventEnum::SAVED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $spies[$event]->shouldHaveReceived('__invoke');
            $callback->times($users->count())
                ->withArgs(
                    function (...$args) use ($model): bool {
                        self::assertCount(1, $args);
                        /** @var User $user */
                        $user = $args[0];
                        self::assertInstanceOf($model, $user);
                        self::assertTrue($user->wasRecentlyCreated);
                        self::assertTrue($user->exists);

                        return true;
                    }
                );
        }
    }

    public function models(): array
    {
        return [
            [MySqlUser::class],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateUserCollectionFeature = $this->app->make(GenerateUserCollectionFeature::class);
    }
}
