<?php

namespace Tests\Unit\Scenarios\Create;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * The `deleting` and `deletingMany` events fired while creating already soft-deleted rows,
 * verified through the public bulk API.
 *
 * @internal
 */
final class DispatchDeletingEventsStepTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * A row created with a filled `deleted_at` triggers `deleting` for each model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Deleting);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * When `deleted_at` is empty, the row is not deleted and `deleting` never fires.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testNotTriggeringWhenDeletedAtIsNull(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => null]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Deleting);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * When an earlier event cancels the save, `deleting` is never reached.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $cancellingEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('cancellingEventDataProvider')]
    public function testNotTriggeringWhenPreviousEventReturnedFalse(BulkEventEnum $cancellingEvent): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $this->listenEventReturning($cancellingEvent, [false, false]);
        $listener = $this->listenEvent(BulkEventEnum::Deleting);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * The listener receives a single argument: the model being deleted.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Deleting);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertModelListenerArguments($users, ...func_get_args())
            );
    }

    /**
     * Events that run before `deleting` and can cancel the save.
     *
     * @return array<string, array{\Lapaliv\BulkUpsert\Enums\BulkEventEnum}>
     */
    public static function cancellingEventDataProvider(): array
    {
        return [
            'saving' => [BulkEventEnum::Saving],
            'savingMany' => [BulkEventEnum::SavingMany],
            'creating' => [BulkEventEnum::Creating],
            'creatingMany' => [BulkEventEnum::CreatingMany],
        ];
    }

    /**
     * A chunk with filled `deleted_at` triggers `deletingMany` once.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testManyTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DeletingMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->once();
    }

    /**
     * When nothing in the chunk is soft-deleted, `deletedMany` never fires.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testManyNotTriggeringWhenNothingIsSoftDeleted(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => null]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DeletedMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * When an earlier event cancels the save, `deletingMany` is never reached.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $cancellingEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('cancellingEventDataProvider')]
    public function testManyNotTriggeringWhenPreviousEventReturnedFalse(BulkEventEnum $cancellingEvent): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $this->listenEventReturning($cancellingEvent, [false, false]);
        $listener = $this->listenEvent(BulkEventEnum::DeletingMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * The listener receives two arguments: the collection and the BulkRowCollection object.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testManyListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DeletingMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertCollectionListenerArguments($users, ...func_get_args())
            );
    }
}
