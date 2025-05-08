<?php

namespace Tests\Unit\Bulk\Delete;

use Lapaliv\BulkUpsert\Collections\BulkRows;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution;
use Tests\App\Collection\PostCollection;
use Tests\App\Collection\UserCollection;
use Tests\App\Models\Post;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\App\Support\TestCallback;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;
use Mockery;

/**
 * @internal
 */
class FireEventsTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @param string $forceDeleteEvent
     * @param string $deleteEvent
     * @param string $deleteManyEvent
     * @param string $forceDeleteManyEvent
     *
     * @return void
     *
     * @throws BulkBindingResolution
     * @throws BulkException
     *
     * @dataProvider modelWithSoftDeletingDataProvider
     */
    public function testFiringSoftDelete(
        string $forceDeleteEvent,
        string $deleteEvent,
        string $deleteManyEvent,
        string $forceDeleteManyEvent,
    ): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $sut = User::query()->bulk();

        $forceDeletingCallback = Mockery::spy(TestCallback::class);
        $deletingCallback = Mockery::spy(TestCallback::class);
        $deletingManyCallback = Mockery::spy(TestCallback::class);
        $forceDeletingManyCallback = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listen($forceDeleteEvent, $forceDeletingCallback);
        Observer::listen($deleteEvent, $deletingCallback);
        Observer::listen($deleteManyEvent, $deletingManyCallback);
        Observer::listen($forceDeleteManyEvent, $forceDeletingManyCallback);

        // act
        $sut->delete($users);

        // assert
        $modelListenerUserIndex = 0;

        self::spyShouldHaveReceived($deletingCallback)
            ->twice()
            ->withArgs(
                function (User $user) use ($users, &$modelListenerUserIndex): bool {
                    if ($user->id === $users->get($modelListenerUserIndex)->id) {
                        ++$modelListenerUserIndex;

                        return $modelListenerUserIndex <= 2;
                    }

                    return false;
                }
            );
        self::spyShouldHaveReceived($deletingManyCallback)
            ->once()
            ->withArgs(
                function (UserCollection $actualUsers, BulkRows $bulkRows) use ($users, &$modelListenerUserIndex): bool {
                    return $actualUsers->count() === $users->count()
                        && $users->where('id', $actualUsers->get(0)->id)->isNotEmpty()
                        && $users->where('id', $actualUsers->get(1)->id)->isNotEmpty()
                        && $actualUsers->get(0)->id !== $actualUsers->get(1)->id
                        && $bulkRows->count() === $users->count()
                        && $bulkRows->get(0)->original === $users->get(0)
                        && $bulkRows->get(1)->original === $users->get(1)
                        && $bulkRows->get(0)->model === $actualUsers->get(0)
                        && $bulkRows->get(1)->model === $actualUsers->get(1)
                        && $bulkRows->get(0)->unique === ['id']
                        && $bulkRows->get(1)->unique === ['id']
                        && $modelListenerUserIndex === 2;
                }
            );
        self::spyShouldNotHaveReceived($forceDeletingCallback);
        self::spyShouldNotHaveReceived($forceDeletingManyCallback);
    }

    /**
     * @param string $forceDeleteEvent
     * @param string $deleteEvent
     * @param string $deleteManyEvent
     * @param string $forceDeleteManyEvent
     *
     * @return void
     *
     * @throws BulkBindingResolution
     * @throws BulkException
     *
     * @dataProvider modelWithSoftDeletingDataProvider
     */
    public function testFiringForceDeleting(
        string $forceDeleteEvent,
        string $deleteEvent,
        string $deleteManyEvent,
        string $forceDeleteManyEvent,
    ): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $sut = User::query()->bulk();

        $forceDeletingListener = Mockery::spy(TestCallback::class);
        $deletingListener = Mockery::spy(TestCallback::class);
        $deletingManyListener = Mockery::spy(TestCallback::class);
        $forceDeletingManyListener = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listen($forceDeleteEvent, $forceDeletingListener);
        Observer::listen($deleteEvent, $deletingListener);
        Observer::listen($deleteManyEvent, $deletingManyListener);
        Observer::listen($forceDeleteManyEvent, $forceDeletingManyListener);

        // act
        $sut->forceDelete($users);

        // assert
        $modelListenerUserIndex = 0;

        self::spyShouldHaveReceived($forceDeletingListener)
            ->twice()
            ->withArgs(
                function (User $user) use ($users, &$modelListenerUserIndex): bool {
                    if ($user->id === $users->get($modelListenerUserIndex)->id) {
                        ++$modelListenerUserIndex;

                        return $modelListenerUserIndex <= 2;
                    }

                    return false;
                }
            );

        self::spyShouldHaveReceived($deletingListener)
            ->twice()
            ->withArgs(
                function (User $user) use ($users, &$modelListenerUserIndex): bool {
                    if ($user->id === $users->get($modelListenerUserIndex - 2)->id) {
                        ++$modelListenerUserIndex;

                        return $modelListenerUserIndex >= 3 && $modelListenerUserIndex <= 4;
                    }

                    return false;
                }
            );

        self::spyShouldHaveReceived($deletingManyListener)
            ->once()
            ->withArgs(
                function (UserCollection $actualUsers, BulkRows $bulkRows) use ($users, &$modelListenerUserIndex): bool {
                    if ($modelListenerUserIndex === 4) {
                        ++$modelListenerUserIndex;
                    } else {
                        return false;
                    }

                    return $actualUsers->count() === $users->count()
                        && $users->where('id', $actualUsers->get(0)->id)->isNotEmpty()
                        && $users->where('id', $actualUsers->get(1)->id)->isNotEmpty()
                        && $actualUsers->get(0)->id !== $actualUsers->get(1)->id
                        && $bulkRows->count() === $users->count()
                        && $bulkRows->get(0)->original === $users->get(0)
                        && $bulkRows->get(1)->original === $users->get(1)
                        && $bulkRows->get(0)->model === $actualUsers->get(0)
                        && $bulkRows->get(1)->model === $actualUsers->get(1)
                        && $bulkRows->get(0)->unique === ['id']
                        && $bulkRows->get(1)->unique === ['id'];
                }
            );

        self::spyShouldHaveReceived($forceDeletingManyListener)
            ->once()
            ->withArgs(
                function (UserCollection $actualUsers, BulkRows $bulkRows) use ($users, &$modelListenerUserIndex): bool {
                    return $actualUsers->count() === $users->count()
                        && $users->where('id', $actualUsers->get(0)->id)->isNotEmpty()
                        && $users->where('id', $actualUsers->get(1)->id)->isNotEmpty()
                        && $actualUsers->get(0)->id !== $actualUsers->get(1)->id
                        && $bulkRows->count() === $users->count()
                        && $bulkRows->get(0)->original === $users->get(0)
                        && $bulkRows->get(1)->original === $users->get(1)
                        && $bulkRows->get(0)->model === $actualUsers->get(0)
                        && $bulkRows->get(1)->model === $actualUsers->get(1)
                        && $bulkRows->get(0)->unique === ['id']
                        && $bulkRows->get(1)->unique === ['id']
                        && $modelListenerUserIndex === 5;
                }
            );
    }

    /**
     * @param string $forceDeleteEvent
     * @param string $deleteEvent
     * @param string $deleteManyEvent
     * @param string $forceDeleteManyEvent
     *
     * @return void
     *
     * @throws BulkBindingResolution
     * @throws BulkException
     *
     * @dataProvider modelWithoutSoftDeletingDataProvider
     */
    public function testFiringDeletingWithoutSoft(
        string $forceDeleteEvent,
        string $deleteEvent,
        string $deleteManyEvent,
        string $forceDeleteManyEvent,
    ): void
    {
        // arrange
        /** @var PostCollection $posts */
        $posts = Post::factory()->count(2)->create();
        $sut = Post::query()->bulk();

        $forceDeletingListener = Mockery::spy(TestCallback::class);
        $deletingListener = Mockery::spy(TestCallback::class);
        $deletingManyListener = Mockery::spy(TestCallback::class);
        $forceDeletingManyListener = Mockery::spy(TestCallback::class);

        Post::observe(Observer::class);
        Observer::listen($forceDeleteEvent, $forceDeletingListener);
        Observer::listen($deleteEvent, $deletingListener);
        Observer::listen($deleteManyEvent, $deletingManyListener);
        Observer::listen($forceDeleteManyEvent, $forceDeletingManyListener);

        // act
        $sut->delete($posts);

        // assert
        $modelListenerPostIndex = 0;

        self::spyShouldHaveReceived($deletingListener)
            ->twice()
            ->withArgs(
                function (Post $post) use ($posts, &$modelListenerPostIndex): bool {
                    if ($post->id === $posts->get($modelListenerPostIndex)->id) {
                        ++$modelListenerPostIndex;

                        return $modelListenerPostIndex <= 2;
                    }

                    return false;
                }
            );

        self::spyShouldHaveReceived($deletingManyListener)
            ->once()
            ->withArgs(
                function (PostCollection $actualPosts, BulkRows $bulkRows) use ($posts, &$modelListenerPostIndex): bool {
                    return $actualPosts->count() === $posts->count()
                        && $posts->where('id', $actualPosts->get(0)->id)->isNotEmpty()
                        && $posts->where('id', $actualPosts->get(1)->id)->isNotEmpty()
                        && $actualPosts->get(0)->id !== $actualPosts->get(1)->id
                        && $bulkRows->count() === $posts->count()
                        && $bulkRows->get(0)->original === $posts->get(0)
                        && $bulkRows->get(1)->original === $posts->get(1)
                        && $bulkRows->get(0)->model === $actualPosts->get(0)
                        && $bulkRows->get(1)->model === $actualPosts->get(1)
                        && $bulkRows->get(0)->unique === ['id']
                        && $bulkRows->get(1)->unique === ['id']
                        && $modelListenerPostIndex === 2;
                }
            );

        self::spyShouldNotHaveReceived($forceDeletingListener);
        self::spyShouldNotHaveReceived($forceDeletingManyListener);
    }

    public function modelWithSoftDeletingDataProvider(): array
    {
        return [
            '-ing events' => [
                BulkEventEnum::FORCE_DELETING,
                BulkEventEnum::DELETING,
                BulkEventEnum::DELETING_MANY,
                BulkEventEnum::FORCE_DELETING_MANY,
            ],
            '-ed events' => [
                BulkEventEnum::FORCE_DELETED,
                BulkEventEnum::DELETED,
                BulkEventEnum::DELETED_MANY,
                BulkEventEnum::FORCE_DELETED_MANY,
            ],
        ];
    }

    public function modelWithoutSoftDeletingDataProvider(): array
    {
        return [
            '-ing events' => [
                BulkEventEnum::FORCE_DELETING,
                BulkEventEnum::DELETING,
                BulkEventEnum::DELETING_MANY,
                BulkEventEnum::FORCE_DELETING_MANY,
            ],
            '-ed events' => [
                BulkEventEnum::FORCE_DELETED,
                BulkEventEnum::DELETED,
                BulkEventEnum::DELETED_MANY,
                BulkEventEnum::FORCE_DELETED_MANY,
            ],
        ];
    }
}
