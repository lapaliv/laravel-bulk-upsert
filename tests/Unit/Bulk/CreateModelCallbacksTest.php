<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk;

use Illuminate\Support\Str;
use Lapaliv\BulkUpsert\Bulk;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Support\TestCallback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Mockery;
use Mockery\VerificationDirector;

/**
 * @internal
 */
class CreateModelCallbacksTest extends TestCase
{
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
     * @param string $eventName
     *
     * @return void
     *
     * @dataProvider methodAndEventDataProvider
     */
    public function testCallbackChangedAttribute(string $methodName, string $eventName): void
    {
        // arrange
        $newName = Str::random();
        MySqlUser::{$eventName}(function (User $user) use ($newName): void {
            $user->name = $newName;
        });
        $user = MySqlUser::factory()->make();
        $sut = new Bulk($user);
        $sut->uniqueBy(['email'])->chunk(1);

        // act
        $sut->{$methodName}([$user]);

        // assert
        $this->assertDatabaseHas($user->getTable(), [
            'name' => $newName,
            'email' => $user->email,
        ], $user->getConnectionName());
    }

    public function methodAndEventDataProvider(): array
    {
        $methods = $this->methodNamesDataProvider();
        $events = ['saving', 'creating'];
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
