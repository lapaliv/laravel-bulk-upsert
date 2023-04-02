<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Bulk;
use Lapaliv\BulkUpsert\Collection\BulkRows;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Support\TestCallback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Mockery;
use Mockery\VerificationDirector;

/**
 * @internal
 *
 * @coversNothing
 */
final class CreateAnyTest extends TestCase
{
    /**
     * @param string $methodName
     *
     * @return void
     *
     * @dataProvider methodNamesDataProvider
     */
    public function testBasicCallbacks(string $methodName): void
    {
        // arrange
        $spies = [];

        foreach (BulkEventEnum::cases() as $event) {
            $spy = Mockery::spy(TestCallback::class);
            $spies[$event] = $spy;
            MySqlUser::{$event}($spy);
        }
        $users = MySqlUser::factory()
            ->count(2)
            ->make();
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($users->count());

        // act
        $sut->{$methodName}($users);

        // assert
        $modelEvents = [
            BulkEventEnum::CREATING,
            BulkEventEnum::CREATED,
            BulkEventEnum::SAVING,
            BulkEventEnum::SAVED,
        ];

        foreach ($modelEvents as $event) {
            /** @var VerificationDirector $spy */
            $spy = $spies[$event]->shouldHaveReceived('__invoke');
            $spy->times($users->count())->withArgs(fn (User $user) => true);
        }

        $collectionEvents = [
            BulkEventEnum::CREATING_MANY,
            BulkEventEnum::CREATED_MANY,
            BulkEventEnum::SAVING_MANY,
            BulkEventEnum::SAVED_MANY,
        ];

        foreach ($collectionEvents as $event) {
            /** @var VerificationDirector $spy */
            $spy = $spies[$event]->shouldHaveReceived('__invoke');
            $spy->times(1)->withArgs(
                fn (UserCollection $users, BulkRows $bulkRows): bool => $users->count() === count($bulkRows)
            );
        }

        foreach (BulkEventEnum::cases() as $event) {
            if (in_array($event, $modelEvents, true) || in_array($event, $collectionEvents, true)) {
                continue;
            }

            $spies[$event]->shouldNotHaveBeenCalled(['__invoke']);
        }
    }

    /**
     * @param string $methodName
     *
     * @return void
     *
     * @dataProvider methodNamesDataProvider
     */
    public function testDisabledCallbacks(string $methodName): void
    {
        // arrange
        $spies = [];

        foreach (BulkEventEnum::cases() as $event) {
            $spy = Mockery::spy(TestCallback::class);
            $spies[$event] = $spy;
            MySqlUser::{$event}($spy);
        }
        $users = MySqlUser::factory()
            ->count(2)
            ->make();
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($users->count())
            ->disableEvents();

        // act
        $sut->{$methodName}($users);

        // assert
        foreach (BulkEventEnum::cases() as $event) {
            $spies[$event]->shouldNotHaveBeenCalled(['__invoke']);
        }
    }

    /**
     * @param string $methodName
     *
     * @return void
     *
     * @dataProvider methodNamesDataProvider
     */
    public function testSoftDeletingCallbacks(string $methodName): void
    {
        // arrange
        $spies = [
            BulkEventEnum::DELETING => Mockery::spy(TestCallback::class),
            BulkEventEnum::DELETED => Mockery::spy(TestCallback::class),
            BulkEventEnum::DELETING_MANY => Mockery::spy(TestCallback::class),
            BulkEventEnum::DELETED_MANY => Mockery::spy(TestCallback::class),
        ];

        foreach ($spies as $event => $spy) {
            MySqlUser::registerModelEvent($event, $spy);
        }

        Carbon::setTestNow(Carbon::now());
        $users = MySqlUser::factory()
            ->count(2)
            ->make([
                'deleted_at' => Carbon::now(),
            ]);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($users->count());

        // act
        $sut->{$methodName}($users);

        // assert
        [$modelEvents, $collectionEvents] = array_chunk($spies, 2, true);

        foreach ($modelEvents as $event => $spy) {
            /** @var VerificationDirector $verification */
            $verification = $spy->shouldHaveReceived('__invoke');
            $verification->times($users->count())->withArgs(fn (User $user) => true);
        }

        foreach ($collectionEvents as $spy) {
            /** @var VerificationDirector $verification */
            $verification = $spy->shouldHaveReceived('__invoke');
            $verification->times(1)->withArgs(
                fn (UserCollection $users, BulkRows $bulkRows): bool => $users->count() === count($bulkRows)
            );
        }
    }

    /**
     * @param string $methodName
     *
     * @return void
     *
     * @dataProvider methodNamesDataProvider
     */
    public function testSoftDeleting(string $methodName): void
    {
        // arrange
        Carbon::setTestNow(Carbon::now());
        $users = MySqlUser::factory()
            ->count(2)
            ->make([
                'deleted_at' => Carbon::now(),
            ]);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($users->count());

        // act
        $sut->{$methodName}($users);

        // assert
        $users->each(
            function (User $user) {
                $this->assertDatabaseHas(MySqlUser::table(), [
                    'name' => $user->name,
                    'email' => $user->email,
                    'gender' => $user->gender->value,
                    'avatar' => $user->avatar,
                    'posts_count' => $user->posts_count,
                    'is_admin' => $user->is_admin,
                    'balance' => $user->balance,
                    'birthday' => $user->birthday?->toDateString(),
                    'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                    'deleted_at' => Carbon::now()->toDateTimeString(),
                ], $user->getConnectionName());

                $model = MySqlUser::query()
                    ->onlyTrashed()
                    ->where('email', $user->email)
                    ->firstOrFail();

                self::assertEquals($user->phones, $model->phones);
            }
        );
    }

    /**
     * @param string $methodName
     *
     * @return void
     *
     * @dataProvider methodNamesDataProvider
     */
    public function testUniqueBy(string $methodName): void
    {
        // arrange
        Carbon::setTestNow(Carbon::now());
        $users = new UserCollection([
            ...MySqlUser::factory()->count(2)->make(['update_uuid' => null]),
            ...MySqlUser::factory()->count(2)->make(['email' => null]),
        ]);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->orUniqueBy(['update_uuid'])
            ->chunk($users->count());

        // act
        $sut->{$methodName}($users);

        // assert
        $users->each(
            function (User $user) {
                $this->assertDatabaseHas(MySqlUser::table(), [
                    'name' => $user->name,
                    'email' => $user->email,
                    'gender' => $user->gender->value,
                    'avatar' => $user->avatar,
                    'posts_count' => $user->posts_count,
                    'is_admin' => $user->is_admin,
                    'balance' => $user->balance,
                    'birthday' => $user->birthday?->toDateString(),
                    'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
                    'update_uuid' => $user->update_uuid,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ], $user->getConnectionName());

                $model = MySqlUser::query()
                    ->where('email', $user->email)
                    ->where('update_uuid', $user->update_uuid)
                    ->firstOrFail();

                self::assertEquals($user->phones, $model->phones);
            }
        );
    }

    /**
     * @param string $methodName
     * @param string $eventName
     *
     * @return void
     *
     * @dataProvider basicModelListenerReturnsFalseDataProvider
     */
    public function testBasicModelListenerReturnsFalse(string $methodName, string $eventName): void
    {
        // arrange
        $mock = Mockery::mock(TestCallback::class);
        $mock->expects('__invoke')
            ->once()
            ->andReturnFalse();
        MySqlUser::{$eventName}($mock);
        $user = MySqlUser::factory()->make();
        $sut = new Bulk($user);
        $sut->uniqueBy(['email'])->chunk(1);

        // act
        $sut->{$methodName}([$user]);

        // assert
        $this->assertDatabaseMissing($user->getTable(), [
            'email' => $user->email,
        ], $user->getConnectionName());
    }

    /**
     * @param string $methodName
     *
     * @return void
     *
     * @dataProvider methodNamesDataProvider
     */
    public function testDeletingModelListenerReturnsFalse(string $methodName): void
    {
        // arrange
        Carbon::setTestNow(Carbon::now());
        $mock = Mockery::mock(TestCallback::class);
        $mock->expects('__invoke')
            ->once()
            ->andReturnFalse();
        MySqlUser::deleting($mock);
        $user = MySqlUser::factory()->make([
            'deleted_at' => Carbon::now(),
        ]);
        $sut = new Bulk($user);
        $sut->uniqueBy(['email'])->chunk(1);

        // act
        $sut->{$methodName}([$user]);

        // assert
        $this->assertDatabaseMissing($user->getTable(), [
            'email' => $user->email,
        ], $user->getConnectionName());
    }

    /**
     * @param string $methodName
     * @param string $eventName
     *
     * @return void
     *
     * @dataProvider basicCollectionListenerReturnsFalseDataProvider
     */
    public function testBasicCollectionListenerReturnsFalse(string $methodName, string $eventName): void
    {
        // arrange
        $mock = Mockery::mock(TestCallback::class);
        $mock->expects('__invoke')
            ->once()
            ->andReturnFalse();
        MySqlUser::{$eventName}($mock);
        $user = MySqlUser::factory()->make();
        $sut = new Bulk($user);
        $sut->uniqueBy(['email'])->chunk(1);

        // act
        $sut->{$methodName}([$user]);

        // assert
        $this->assertDatabaseMissing($user->getTable(), [
            'email' => $user->email,
        ], $user->getConnectionName());
    }

    /**
     * @param string $methodName
     *
     * @return void
     *
     * @dataProvider methodNamesDataProvider
     */
    public function testDeletingCollectionListenerReturnsFalse(string $methodName): void
    {
        // arrange
        Carbon::setTestNow(Carbon::now());
        $mock = Mockery::mock(TestCallback::class);
        $mock->expects('__invoke')
            ->once()
            ->andReturnFalse();
        MySqlUser::deletingMany($mock);
        $user = MySqlUser::factory()->make([
            'deleted_at' => Carbon::now(),
        ]);
        $sut = new Bulk($user);
        $sut->uniqueBy(['email'])->chunk(1);

        // act
        $sut->{$methodName}([$user]);

        // assert
        $this->assertDatabaseMissing($user->getTable(), [
            'email' => $user->email,
        ], $user->getConnectionName());
    }

    /**
     * @param string $methodName
     *
     * @return void
     *
     * @dataProvider methodNamesDataProvider
     */
    public function testModelCallbacksArguments(string $methodName): void
    {
        // arrange
        $spies = [
            BulkEventEnum::CREATING => Mockery::spy(TestCallback::class),
            BulkEventEnum::SAVING => Mockery::spy(TestCallback::class),
            BulkEventEnum::CREATED => Mockery::spy(TestCallback::class),
            BulkEventEnum::SAVED => Mockery::spy(TestCallback::class),
        ];

        MySqlUser::creating($spies[BulkEventEnum::CREATING]);
        MySqlUser::saving($spies[BulkEventEnum::SAVING]);
        MySqlUser::created($spies[BulkEventEnum::CREATED]);
        MySqlUser::saved($spies[BulkEventEnum::SAVED]);

        $expectedUser = MySqlUser::factory()->make();

        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk(1);

        // act
        $sut->{$methodName}([$expectedUser]);

        // assert
        foreach ($spies as $event => $spy) {
            /** @var VerificationDirector $verification */
            $verification = $spy->shouldHaveBeenCalled();
            $verification->times(1)->withArgs(
                function () use ($expectedUser): bool {
                    return count(func_get_args()) === 1
                        && func_get_args()[0] instanceof User
                        && func_get_args()[0]->email === $expectedUser->email;
                }
            );
        }
    }

    /**
     * @param string $methodName
     *
     * @return void
     *
     * @dataProvider methodNamesDataProvider
     */
    public function testCollectionCallbacksArguments(string $methodName): void
    {
        // arrange
        $spies = [
            BulkEventEnum::CREATING_MANY => Mockery::spy(TestCallback::class),
            BulkEventEnum::SAVING_MANY => Mockery::spy(TestCallback::class),
            BulkEventEnum::CREATED_MANY => Mockery::spy(TestCallback::class),
            BulkEventEnum::SAVED_MANY => Mockery::spy(TestCallback::class),
        ];

        MySqlUser::creatingMany($spies[BulkEventEnum::CREATING_MANY]);
        MySqlUser::savingMany($spies[BulkEventEnum::SAVING_MANY]);
        MySqlUser::createdMany($spies[BulkEventEnum::CREATED_MANY]);
        MySqlUser::savedMany($spies[BulkEventEnum::SAVED_MANY]);

        $expectedUser = MySqlUser::factory()->make();

        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk(1);

        // act
        $sut->{$methodName}([$expectedUser]);

        // assert
        foreach ($spies as $spy) {
            /** @var VerificationDirector $verification */
            $verification = $spy->shouldHaveBeenCalled();
            $verification->times(1)->withArgs(
                function () use ($expectedUser): bool {
                    $args = func_get_args();

                    return count($args) === 2
                        && $args[0] instanceof UserCollection
                        && $args[1] instanceof BulkRows
                        && $args[0][0]->email === $expectedUser->email
                        && $args[1]->count() === 1
                        && $args[1][0]->unique === ['email']
                        && $args[1][0]->model->email === $expectedUser->email
                        && $args[1][0]->wasSkipped === false;
                }
            );
        }
    }

    public function methodNamesDataProvider(): array
    {
        return [
            'create' => ['create'],
            'createOrAccumulate' => ['createOrAccumulate'],
            'createAndReturn' => ['createAndReturn'],
        ];
    }

    public function basicModelListenerReturnsFalseDataProvider(): array
    {
        return [
            'create method and saving event' => [
                'create',
                'saving',
            ],
            'create method and creating event' => [
                'create',
                'creating',
            ],
            'createOrAccumulate method and saving event' => [
                'createOrAccumulate',
                'saving',
            ],
            'createOrAccumulate method and creating event' => [
                'createOrAccumulate',
                'creating',
            ],
            'createAndReturn method and saving event' => [
                'createAndReturn',
                'saving',
            ],
            'createAndReturn method and creating event' => [
                'createAndReturn',
                'creating',
            ],
        ];
    }

    public function basicCollectionListenerReturnsFalseDataProvider(): array
    {
        return [
            'create method and savingMany event' => [
                'create',
                'savingMany',
            ],
            'create method and creatingMany event' => [
                'create',
                'creatingMany',
            ],
            'createOrAccumulate method and savingMany event' => [
                'createOrAccumulate',
                'savingMany',
            ],
            'createOrAccumulate method and creatingMany event' => [
                'createOrAccumulate',
                'creatingMany',
            ],
            'createAndReturn method and savingMany event' => [
                'createAndReturn',
                'savingMany',
            ],
            'createAndReturn method and creatingMany event' => [
                'createAndReturn',
                'creatingMany',
            ],
        ];
    }
}
