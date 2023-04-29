<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Bulk;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Support\TestCallback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Mockery;
use Mockery\VerificationDirector;

/**
 * @internal
 */
class CreateSoftDeletingCallbackTest extends TestCase
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
        Carbon::setTestNow(Carbon::now());
        $spy = Mockery::spy(TestCallback::class);
        MySqlUser::deleting($spy);
        $expectedUser = MySqlUser::factory()->make([
            'deleted_at' => Carbon::now(),
        ]);

        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk(1);

        // act
        $sut->{$methodName}([$expectedUser]);

        // assert
        /** @var VerificationDirector $verification */
        $verification = $spy->shouldHaveBeenCalled();
        $verification->times(1)->withArgs(
            function () use ($expectedUser): bool {
                $args = func_get_args();

                return count($args) === 1
                    && $args[0] instanceof User
                    && $args[0]->email === $expectedUser->email
                    && $args[0]->deleted_at->toDateTimeString() === Carbon::now()->toDateTimeString();
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
    public function testDeletingCallbackReturnedFalse(string $methodName): void
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
        $this->assertDatabaseHas($user->getTable(), [
            'email' => $user->email,
            'deleted_at' => null,
        ], $user->getConnectionName());
    }

    /**
     * @param string $methodName
     *
     * @return void
     *
     * @dataProvider methodNamesDataProvider
     */
    public function testDeletingCallbackChangedDeletedAt(string $methodName): void
    {
        // arrange
        Carbon::setTestNow(Carbon::now());
        $newDeletedAt = Carbon::now()->addMonth();
        MySqlUser::deleting(function (User $user) use ($newDeletedAt): void {
            $user->deleted_at = $newDeletedAt;
        });
        $user = MySqlUser::factory()->make([
            'deleted_at' => Carbon::now(),
        ]);
        $sut = new Bulk($user);
        $sut->uniqueBy(['email'])->chunk(1);

        // act
        $sut->{$methodName}([$user]);

        // assert
        $this->assertDatabaseHas($user->getTable(), [
            'email' => $user->email,
            'deleted_at' => $newDeletedAt->toDateTimeString(),
        ], $user->getConnectionName());
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
