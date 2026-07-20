<?php

namespace Tests\Unit\Scenarios\CreateScenario;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * The `deleted` event fired while creating already soft-deleted rows,
 * verified through the public bulk API.
 *
 * @internal
 */
final class DeletedEventTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * A row created with a filled `deleted_at` triggers `deleted` for each model.
     *
     * @throws BulkException
     */
    public function testTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DELETED);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * The listener receives a single argument: the model that was deleted.
     *
     * @throws BulkException
     */
    public function testListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2, ['deleted_at' => Carbon::now()]);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::DELETED);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertModelListenerArguments($users, ...func_get_args())
            );
    }
}
