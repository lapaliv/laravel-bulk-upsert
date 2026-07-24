<?php

namespace Tests\Unit\Scenarios\Create;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * The `saving` and `savingMany` events fired while creating, verified through the public bulk API.
 *
 * @internal
 */
final class DispatchSavingEventsStepTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * The listener is invoked once per created model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Saving);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * The listener receives a single argument: the model being saved.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Saving);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertModelListenerArguments($users, ...func_get_args())
            );
    }

    /**
     * The listener is invoked once for the whole chunk.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testManyTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::SavingMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->once();
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
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::SavingMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertCollectionListenerArguments($users, ...func_get_args())
            );
    }
}
