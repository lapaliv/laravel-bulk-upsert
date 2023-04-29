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
 */
class CreateSoftDeletingCollectionCallbackTest extends TestCase
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
        $spies = [
            BulkEventEnum::DELETING_MANY => Mockery::spy(TestCallback::class),
            BulkEventEnum::DELETED_MANY => Mockery::spy(TestCallback::class),
        ];

        MySqlUser::deletingMany($spies[BulkEventEnum::DELETING_MANY]);
        MySqlUser::deletedMany($spies[BulkEventEnum::DELETED_MANY]);

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
                        && $args[1][0]->model->deleted_at->toDateTimeString() === Carbon::now()->toDateTimeString();
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
    public function testDeletingCallbackReturnedFalse(string $methodName): void
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
    public function testDeletingCallbackChangedAttribute(string $methodName): void
    {
        // arrange
        Carbon::setTestNow(Carbon::now());
        $newDeletedAt = Carbon::now()->addMonth();
        MySqlUser::deletingMany(
            function (UserCollection $users) use ($newDeletedAt): void {
                $users->each(
                    fn (User $user) => $user->deleted_at = $newDeletedAt
                );
            }
        );
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
