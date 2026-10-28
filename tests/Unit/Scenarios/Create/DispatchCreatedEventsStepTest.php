<?php

namespace Tests\Unit\Scenarios\Create;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * The events ending with '-ed' fired while creating, verified through the public bulk API.
 *
 * The step walks the rows once and fires `created`, `deleted` and `saved` for each of them,
 * so all three families are covered by this one test class.
 *
 * @internal
 */
final class DispatchCreatedEventsStepTest extends TestCaseWrapper
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
    public function testCreatedTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Created);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * The listener receives a single argument: the model that was created.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCreatedListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Created);

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
    public function testCreatedManyTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::CreatedMany);

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
    public function testCreatedManyListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::CreatedMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertCollectionListenerArguments($users, ...func_get_args())
            );
    }

    /**
     * A row created with a filled `deleted_at` triggers `deleted` for each model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeletedTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Deleted);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * The listener receives a single argument: the model that was deleted.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeletedListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Deleted);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertModelListenerArguments($users, ...func_get_args())
            );
    }

    /**
     * A chunk with filled `deleted_at` triggers `deletedMany` once.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeletedManyTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DeletedMany);

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
    public function testDeletedManyListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DeletedMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertCollectionListenerArguments($users, ...func_get_args())
            );
    }

    /**
     * The listener is invoked once per created model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSavedTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Saved);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * The listener receives a single argument: the model that was saved.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSavedListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Saved);

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
    public function testSavedManyTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::SavedMany);

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
    public function testSavedManyListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::SavedMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertCollectionListenerArguments($users, ...func_get_args())
            );
    }
}
