<?php

namespace Tests\Unit\Bulk\Delete;

use Lapaliv\BulkUpsert\Collections\BulkRowCollection;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use PHPUnit\Framework\Attributes\DataProvider;
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
 * The delete events which the package fires for `delete()` and for `forceDelete()`.
 *
 * @internal
 */
class DeleteEventsTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * A soft delete fires the delete event per model and the collection one after them.
     *
     * The force delete events stay silent, and the collection listener gets the models together
     * with the bulk rows which point back at the original models.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $forceDeleteEvent
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $deleteEvent
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $deleteManyEvent
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $forceDeleteManyEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('modelWithSoftDeletingDataProvider')]
    public function testFiringSoftDelete(
        BulkEventEnum $forceDeleteEvent,
        BulkEventEnum $deleteEvent,
        BulkEventEnum $deleteManyEvent,
        BulkEventEnum $forceDeleteManyEvent,
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
                function (UserCollection $actualUsers, BulkRowCollection $bulkRows) use ($users, &$modelListenerUserIndex): bool {
                    return $actualUsers->count() === $users->count()
                        && $users->where('id', $actualUsers->get(0)->id)->isNotEmpty()
                        && $users->where('id', $actualUsers->get(1)->id)->isNotEmpty()
                        && $actualUsers->get(0)->id !== $actualUsers->get(1)->id
                        && $bulkRows->count() === $users->count()
                        && $bulkRows->get(0)->getOriginal() === $users->get(0)
                        && $bulkRows->get(1)->getOriginal() === $users->get(1)
                        && $bulkRows->get(0)->getModel() === $actualUsers->get(0)
                        && $bulkRows->get(1)->getModel() === $actualUsers->get(1)
                        && $bulkRows->get(0)->getUnique() === ['id']
                        && $bulkRows->get(1)->getUnique() === ['id']
                        && $modelListenerUserIndex === 2;
                }
            );
        self::spyShouldNotHaveReceived($forceDeletingCallback);
        self::spyShouldNotHaveReceived($forceDeletingManyCallback);
    }

    /**
     * A force delete fires the force delete and the delete events in a fixed order.
     *
     * Both model events run for every model first, then the delete collection event, and the
     * force delete collection event comes last.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $forceDeleteEvent
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $deleteEvent
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $deleteManyEvent
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $forceDeleteManyEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('modelWithSoftDeletingDataProvider')]
    public function testFiringForceDeleting(
        BulkEventEnum $forceDeleteEvent,
        BulkEventEnum $deleteEvent,
        BulkEventEnum $deleteManyEvent,
        BulkEventEnum $forceDeleteManyEvent,
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
                function (UserCollection $actualUsers, BulkRowCollection $bulkRows) use ($users, &$modelListenerUserIndex): bool {
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
                        && $bulkRows->get(0)->getOriginal() === $users->get(0)
                        && $bulkRows->get(1)->getOriginal() === $users->get(1)
                        && $bulkRows->get(0)->getModel() === $actualUsers->get(0)
                        && $bulkRows->get(1)->getModel() === $actualUsers->get(1)
                        && $bulkRows->get(0)->getUnique() === ['id']
                        && $bulkRows->get(1)->getUnique() === ['id'];
                }
            );

        self::spyShouldHaveReceived($forceDeletingManyListener)
            ->once()
            ->withArgs(
                function (UserCollection $actualUsers, BulkRowCollection $bulkRows) use ($users, &$modelListenerUserIndex): bool {
                    return $actualUsers->count() === $users->count()
                        && $users->where('id', $actualUsers->get(0)->id)->isNotEmpty()
                        && $users->where('id', $actualUsers->get(1)->id)->isNotEmpty()
                        && $actualUsers->get(0)->id !== $actualUsers->get(1)->id
                        && $bulkRows->count() === $users->count()
                        && $bulkRows->get(0)->getOriginal() === $users->get(0)
                        && $bulkRows->get(1)->getOriginal() === $users->get(1)
                        && $bulkRows->get(0)->getModel() === $actualUsers->get(0)
                        && $bulkRows->get(1)->getModel() === $actualUsers->get(1)
                        && $bulkRows->get(0)->getUnique() === ['id']
                        && $bulkRows->get(1)->getUnique() === ['id']
                        && $modelListenerUserIndex === 5;
                }
            );
    }

    /**
     * A model without soft deleting fires only the delete events, never the force delete ones.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $forceDeleteEvent
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $deleteEvent
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $deleteManyEvent
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $forceDeleteManyEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('modelWithoutSoftDeletingDataProvider')]
    public function testFiringDeletingWithoutSoft(
        BulkEventEnum $forceDeleteEvent,
        BulkEventEnum $deleteEvent,
        BulkEventEnum $deleteManyEvent,
        BulkEventEnum $forceDeleteManyEvent,
    ): void
    {
        // arrange
        /** @var \Tests\App\Collection\PostCollection $posts */
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
                function (PostCollection $actualPosts, BulkRowCollection $bulkRows) use ($posts, &$modelListenerPostIndex): bool {
                    return $actualPosts->count() === $posts->count()
                        && $posts->where('id', $actualPosts->get(0)->id)->isNotEmpty()
                        && $posts->where('id', $actualPosts->get(1)->id)->isNotEmpty()
                        && $actualPosts->get(0)->id !== $actualPosts->get(1)->id
                        && $bulkRows->count() === $posts->count()
                        && $bulkRows->get(0)->getOriginal() === $posts->get(0)
                        && $bulkRows->get(1)->getOriginal() === $posts->get(1)
                        && $bulkRows->get(0)->getModel() === $actualPosts->get(0)
                        && $bulkRows->get(1)->getModel() === $actualPosts->get(1)
                        && $bulkRows->get(0)->getUnique() === ['id']
                        && $bulkRows->get(1)->getUnique() === ['id']
                        && $modelListenerPostIndex === 2;
                }
            );

        self::spyShouldNotHaveReceived($forceDeletingListener);
        self::spyShouldNotHaveReceived($forceDeletingManyListener);
    }

    /**
     * The delete events of a model with soft deleting, once for `-ing` and once for `-ed`.
     *
     * @return array<string, array{\Lapaliv\BulkUpsert\Enums\BulkEventEnum, \Lapaliv\BulkUpsert\Enums\BulkEventEnum, \Lapaliv\BulkUpsert\Enums\BulkEventEnum, \Lapaliv\BulkUpsert\Enums\BulkEventEnum}>
     */
    public static function modelWithSoftDeletingDataProvider(): array
    {
        return [
            '-ing events' => [
                BulkEventEnum::ForceDeleting,
                BulkEventEnum::Deleting,
                BulkEventEnum::DeletingMany,
                BulkEventEnum::ForceDeletingMany,
            ],
            '-ed events' => [
                BulkEventEnum::ForceDeleted,
                BulkEventEnum::Deleted,
                BulkEventEnum::DeletedMany,
                BulkEventEnum::ForceDeletedMany,
            ],
        ];
    }

    /**
     * The delete events of a model without soft deleting, once for `-ing` and once for `-ed`.
     *
     * @return array<string, array{\Lapaliv\BulkUpsert\Enums\BulkEventEnum, \Lapaliv\BulkUpsert\Enums\BulkEventEnum, \Lapaliv\BulkUpsert\Enums\BulkEventEnum, \Lapaliv\BulkUpsert\Enums\BulkEventEnum}>
     */
    public static function modelWithoutSoftDeletingDataProvider(): array
    {
        return [
            '-ing events' => [
                BulkEventEnum::ForceDeleting,
                BulkEventEnum::Deleting,
                BulkEventEnum::DeletingMany,
                BulkEventEnum::ForceDeletingMany,
            ],
            '-ed events' => [
                BulkEventEnum::ForceDeleted,
                BulkEventEnum::Deleted,
                BulkEventEnum::DeletedMany,
                BulkEventEnum::ForceDeletedMany,
            ],
        ];
    }
}
