<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk;

use Illuminate\Support\Str;
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
 */
class CreateCollectionCallbacksTest extends TestCase
{
    /**
     * @param string $methodName
     * @param string $eventName
     *
     * @return void
     *
     * @dataProvider methodAndEventDataProvider
     */
    public function testCallbackReturnedFalse(string $methodName, string $eventName): void
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
    public function testArguments(string $methodName): void
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
                        && $args[1][0]->model->email === $expectedUser->email;
                }
            );
        }
    }

    /**
     * @param string $methodName
     * @param string $event
     *
     * @return void
     *
     * @dataProvider methodAndEventDataProvider
     */
    public function testCallbackChangedAttribute(string $methodName, string $event): void
    {
        // arrange
        $newName = Str::random();
        MySqlUser::{$event}(
            function (UserCollection $users) use ($newName): void {
                $users->each(
                    fn (User $user) => $user->name = $newName
                );
            }
        );

        $expectedUser = MySqlUser::factory()->make();

        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk(1);

        // act
        $sut->{$methodName}([$expectedUser]);

        // assert
        $this->assertDatabaseHas($expectedUser->getTable(), [
            'name' => $expectedUser->name,
            'email' => $expectedUser->email,
        ], $expectedUser->getConnectionName());
    }

    public function methodAndEventDataProvider(): array
    {
        $methods = $this->methodNamesDataProvider();
        $events = ['savingMany', 'creatingMany'];
        $result = [];

        foreach ($methods as $key => $params) {
            foreach ($events as $event) {
                $result["{$key}.{$event}"] = [$params[0], $event];
            }
        }

        return $result;
    }

    public function methodNamesDataProvider(): array
    {
        return [
            'create' => ['create'],
            'createOrAccumulate' => ['createOrAccumulate'],
            'createAndReturn' => ['createAndReturn'],
        ];
    }
}
