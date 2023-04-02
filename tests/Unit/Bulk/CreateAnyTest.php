<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk;

use Carbon\Carbon;
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
     * @dataProvider dataProvider
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
     * @dataProvider dataProvider
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

    public function dataProvider(): array
    {
        return [
            'create' => ['create'],
            'createOrAccumulate' => ['createOrAccumulate'],
            'createAndReturn' => ['createAndReturn'],
        ];
    }
}
