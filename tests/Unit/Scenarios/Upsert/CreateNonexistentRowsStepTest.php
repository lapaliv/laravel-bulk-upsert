<?php

namespace Tests\Unit\Scenarios\Upsert;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * How an upsert splits the rows between the insert and the update,
 * verified through the public bulk API.
 *
 * @internal
 */
final class CreateNonexistentRowsStepTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * A row that is absent in the database is inserted and never updated afterwards.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testNonexistentRowIsCreatedOnly(): void
    {
        // arrange
        $user = $this->userGenerator->makeOne();
        User::observe(Observer::class);
        $created = $this->listenEvent(BulkEventEnum::Created);
        $updated = $this->listenEvent(BulkEventEnum::Updated);

        // act
        User::query()->bulk()->uniqueBy(['email'])->upsert([$user]);

        // assert
        self::spyShouldHaveReceived($created)->once();
        self::spyShouldNotHaveReceived($updated);
        $this->userWasCreated($user);
    }

    /**
     * A row that is already in the database is updated and never inserted.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testExistingRowIsUpdatedOnly(): void
    {
        // arrange
        $user = $this->userGenerator->createOneAndDirty();
        User::observe(Observer::class);
        $created = $this->listenEvent(BulkEventEnum::Created);
        $updated = $this->listenEvent(BulkEventEnum::Updated);

        // act
        User::query()->bulk()->uniqueBy(['email'])->upsert([$user]);

        // assert
        self::spyShouldNotHaveReceived($created);
        self::spyShouldHaveReceived($updated)->once();
        $this->userWasUpdated($user);
    }

    /**
     * A mixed chunk sends each row down its own path in a single call.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testMixedChunkIsSplitBetweenBothPaths(): void
    {
        // arrange
        $existing = $this->userGenerator->createOneAndDirty();
        $nonexistent = $this->userGenerator->makeOne();
        User::observe(Observer::class);
        $created = $this->listenEvent(BulkEventEnum::Created);
        $updated = $this->listenEvent(BulkEventEnum::Updated);

        // act
        User::query()->bulk()->uniqueBy(['email'])->upsert([$existing, $nonexistent]);

        // assert
        self::spyShouldHaveReceived($created)->once();
        self::spyShouldHaveReceived($updated)->once();
        $this->userWasUpdated($existing);
        $this->userWasCreated($nonexistent);
    }

    /**
     * A row that appears in the database while the upsert is running falls back
     * to the update instead of being dropped.
     *
     * This is the race the insert's `ignore` flag exists for: the row was absent when the
     * upsert selected the existing ones, so it was routed to the insert, but somebody else
     * inserted the same unique key in between. The insert is silently ignored, and the model is
     * replaced with the stored row — which is why the step puts the caller's attributes back
     * before handing the row to the update. Without that, the caller's data would be dropped
     * without a single event and without an error.
     *
     * The concurrent insert is emulated from a `creatingMany` listener, which runs after the
     * upsert has selected the existing rows and before it inserts anything. It goes through the
     * query builder rather than the factory on purpose, so that Eloquent's own model events do
     * not interfere with the events under test.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowCreatedConcurrentlyFallsBackToTheUpdate(): void
    {
        // arrange
        $user = $this->userGenerator->makeOne(['name' => 'name from the upsert']);
        User::observe(Observer::class);
        Observer::listen(
            BulkEventEnum::CreatingMany,
            function () use ($user): void {
                $attributes = $user->getAttributes();
                $attributes['name'] = 'name from another process';
                $attributes['created_at'] = Carbon::now()->toDateTimeString();
                $attributes['updated_at'] = Carbon::now()->toDateTimeString();

                DB::connection($user->getConnectionName())
                    ->table($user->getTable())
                    ->insert($attributes);
            }
        );

        // act
        User::query()->bulk()->uniqueBy(['email'])->upsert([$user]);

        // assert
        self::assertDatabaseHas($user->getTable(), [
            'email' => $user->email,
            'name' => 'name from the upsert',
        ], $user->getConnectionName());
    }

    /**
     * A nonexistent row may be created already soft deleted.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testNonexistentSoftDeletedRowIsCreated(): void
    {
        // arrange
        $user = $this->userGenerator->makeOne(['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $deleted = $this->listenEvent(BulkEventEnum::Deleted);

        // act
        User::query()->bulk()->uniqueBy(['email'])->upsert([$user]);

        // assert
        self::spyShouldHaveReceived($deleted)->once();
        $this->userWasCreated($user);
    }
}
