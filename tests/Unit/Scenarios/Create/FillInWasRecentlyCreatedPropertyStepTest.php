<?php

namespace Tests\Unit\Scenarios\Create;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\Story;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The `wasRecentlyCreated` property filled in after the insert,
 * verified through the public bulk API.
 *
 * The property is derived in one of two ways depending on the model: from the last primary key
 * the table had before the insert, or from the `created_at` value. Both ways are covered here.
 *
 * @internal
 */
final class FillInWasRecentlyCreatedPropertyStepTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * A freshly inserted auto-incrementing model reports wasRecentlyCreated === true.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testFlagWasRecentlyCreatedWithIncrementing(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        Observer::listen(BulkEventEnum::Saved, function (User $user): void {
            self::assertTrue($user->wasRecentlyCreated);
        });

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users, ignoreConflicts: true);
    }

    /**
     * A freshly inserted model with a non-incrementing key reports wasRecentlyCreated === true.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testFlagWasRecentlyCreatedWithoutIncrementing(): void
    {
        // arrange
        $stories = Story::factory()->count(2)->make();
        Story::observe(Observer::class);
        Observer::listen(BulkEventEnum::Saved, function (Story $story): void {
            self::assertTrue($story->wasRecentlyCreated);
        });

        // act
        Story::query()->bulk()->uniqueBy(['uuid'])->create($stories, ignoreConflicts: true);
    }
}
