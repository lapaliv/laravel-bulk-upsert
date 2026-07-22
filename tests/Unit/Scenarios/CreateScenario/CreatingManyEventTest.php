<?php

namespace Tests\Unit\Scenarios\CreateScenario;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * The `creatingMany` event fired while creating, verified through the public bulk API.
 *
 * @internal
 */
final class CreatingManyEventTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * The listener is invoked once for the whole chunk.
     *
     * @throws BulkException
     */
    public function testTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::CREATING_MANY);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->once();
    }

    /**
     * When an earlier event cancels the save, `creatingMany` is never reached.
     *
     * @throws BulkException
     */
    #[DataProvider('cancellingEventDataProvider')]
    public function testNotTriggeringWhenPreviousEventReturnedFalse(string $cancellingEvent): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $this->listenEventReturning($cancellingEvent, [false, false]);
        $listener = $this->listenEvent(BulkEventEnum::CREATING_MANY);

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
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::CREATING_MANY);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertCollectionListenerArguments($users, ...func_get_args())
            );
    }

    /**
     * Events that run before `creatingMany` and can cancel the save.
     *
     * @return array<string, array{string}>
     */
    public static function cancellingEventDataProvider(): array
    {
        return [
            'saving' => [BulkEventEnum::SAVING],
            'savingMany' => [BulkEventEnum::SAVING_MANY],
        ];
    }
}
