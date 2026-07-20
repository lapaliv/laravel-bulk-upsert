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
 * The `deletingMany` event fired while creating already soft-deleted rows,
 * verified through the public bulk API.
 *
 * @internal
 */
final class DeletingManyEventTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * A chunk with filled `deleted_at` triggers `deletingMany` once.
     *
     * @throws BulkException
     */
    public function testTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DELETING_MANY);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->once();
    }

    /**
     * When nothing is soft-deleted, the deleting collection events never fire.
     *
     * @throws BulkException
     */
    public function testNotTriggeringWhenDeletedAtIsNull(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => null]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DELETED_MANY);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * When an earlier event cancels the save, `deletingMany` is never reached.
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
        $listener = $this->listenEvent(BulkEventEnum::DELETING_MANY);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * The listener receives two arguments: the collection and the BulkRows object.
     *
     * @throws BulkException
     */
    public function testListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DELETING_MANY);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertCollectionListenerArguments($users, ...func_get_args())
            );
    }

    /**
     * Events that run before `deletingMany` and can cancel the save.
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
