<?php

namespace Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * A listener that returns `false` from `saving`/`savingMany` must cancel the write.
 *
 * Regression test.
 * {@see \Lapaliv\BulkUpsert\Scenarios\UpdateScenario::dispatchSavingEvents()}
 * marks the vetoed row with `setSkipSaving(true)` only, but
 * {@see \Lapaliv\BulkUpsert\Builders\UpdateBuilderFactory::handle()} filters rows by
 * `isSkipUpdating()` and builds the WHERE clause from
 * {@see \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity::getNotSkippedUpdatingModels()},
 * which did the same. Nothing on the query-building path read `isSkipSaving()`, so the UPDATE
 * still reached the database with every vetoed row in it — only the events were suppressed.
 * Both now skip a row that either flag marks, and they have to stay in agreement: the SET list
 * and the WHERE clause are built from the two separately.
 *
 * @internal
 */
final class UpdateSavingCancellationTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * `saving` returning false must keep the row untouched in the database.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSavingReturningFalseCancelsTheUpdate(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $storedNames = $this->storedNames($users);

        User::observe(Observer::class);
        $this->listenEventReturning(BulkEventEnum::Saving, [false, false]);

        $sut = User::query()
            ->bulk()
            ->uniqueBy(['id']);

        // act
        $sut->update($users);

        // assert
        $this->assertUsersWereNotUpdated($users, $storedNames);
    }

    /**
     * `savingMany` returning false must keep the whole batch untouched in the database.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSavingManyReturningFalseCancelsTheUpdate(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $storedNames = $this->storedNames($users);

        User::observe(Observer::class);
        $this->listenEventReturning(BulkEventEnum::SavingMany, [false]);

        $sut = User::query()
            ->bulk()
            ->uniqueBy(['id']);

        // act
        $sut->update($users);

        // assert
        $this->assertUsersWereNotUpdated($users, $storedNames);
    }

    /**
     * The names currently stored in the database, keyed by the primary key.
     *
     * @param iterable<int, \Tests\App\Models\User> $users
     *
     * @return array<int, string>
     */
    private function storedNames(iterable $users): array
    {
        $result = [];

        foreach ($users as $user) {
            $result[$user->id] = $user->getOriginal('name');
        }

        return $result;
    }

    /**
     * Assert that the rows still hold their old name and their old `updated_at`.
     *
     * @param iterable<int, \Tests\App\Models\User> $users
     * @param array<int, string> $storedNames
     *
     * @return void
     */
    private function assertUsersWereNotUpdated(iterable $users, array $storedNames): void
    {
        foreach ($users as $user) {
            // The dirty value must not have reached the database.
            $this->assertDatabaseMissing($user->getTable(), [
                'id' => $user->id,
                'name' => $user->name,
            ], $user->getConnectionName());

            // The row must still hold the value it had before the cancelled update,
            // and `updated_at` must not have been bumped either.
            $this->assertDatabaseHas($user->getTable(), [
                'id' => $user->id,
                'name' => $storedNames[$user->id],
            ], $user->getConnectionName());

            $this->assertDatabaseMissing($user->getTable(), [
                'id' => $user->id,
                'updated_at' => Carbon::now()->toDateTimeString(),
            ], $user->getConnectionName());
        }
    }
}
