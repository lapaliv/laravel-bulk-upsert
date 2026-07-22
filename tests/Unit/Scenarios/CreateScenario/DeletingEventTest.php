<?php

namespace Tests\Unit\Scenarios\CreateScenario;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * The `deleting` event fired while creating already soft-deleted rows,
 * verified through the public bulk API.
 *
 * @internal
 */
final class DeletingEventTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * A row created with a filled `deleted_at` triggers `deleting` for each model.
     *
     * @throws BulkException
     */
    public function testTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DELETING);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * When `deleted_at` is empty, the row is not deleted and `deleting` never fires.
     *
     * @throws BulkException
     */
    public function testNotTriggeringWhenDeletedAtIsNull(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => null]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DELETING);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * When an earlier event cancels the save, `deleting` is never reached.
     *
     * @throws BulkException
     */
    #[DataProvider('cancellingEventDataProvider')]
    public function testNotTriggeringWhenPreviousEventReturnedFalse(string $cancellingEvent): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $this->listenEventReturning($cancellingEvent, [false, false]);
        $listener = $this->listenEvent(BulkEventEnum::DELETING);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * The listener receives a single argument: the model being deleted.
     *
     * @throws BulkException
     */
    public function testListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DELETING);

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
     * @return array<string, array{string}>
     */
    public static function cancellingEventDataProvider(): array
    {
        return [
            'saving' => [BulkEventEnum::SAVING],
            'savingMany' => [BulkEventEnum::SAVING_MANY],
            'creating' => [BulkEventEnum::CREATING],
            'creatingMany' => [BulkEventEnum::CREATING_MANY],
        ];
    }
}
