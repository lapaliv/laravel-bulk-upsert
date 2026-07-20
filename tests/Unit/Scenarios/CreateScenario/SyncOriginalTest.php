<?php

namespace Tests\Unit\Scenarios\CreateScenario;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Collection\UserCollection;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * After a create, the resulting models must be "clean" (their original state synced).
 *
 * @internal
 */
final class SyncOriginalTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * The models handed to the `savedMany` listener carry no pending changes.
     *
     * @throws BulkException
     */
    public function test(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $usersFromEvent = null;
        User::observe(Observer::class);
        Observer::listen(
            BulkEventEnum::SAVED_MANY,
            function (UserCollection $users) use (&$usersFromEvent): void {
                $usersFromEvent = $users;
            }
        );

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::assertNotNull($usersFromEvent);

        foreach ($usersFromEvent as $user) {
            self::assertFalse($user->isDirty());
            self::assertEmpty($user->getChanges());
        }
    }
}
