<?php

namespace Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\App\Models\Comment;
use Tests\App\Models\Post;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;

/**
 * Only a row which has been saved touches its parents.
 *
 * Regression test. The touch step took every row of the chunk: a row which the update did
 * not find, or whose saving/updating a listener cancelled, still touched the `updated_at`
 * of its parents, although nothing of it had been written.
 *
 * @internal
 */
final class TouchOnlySavedRowsTest extends TestCaseWrapper
{
    /**
     * The time at which the fixtures are created.
     *
     * @var string
     */
    private const string BEFORE = '2020-01-01 00:00:00';

    /**
     * The user the comments belong to.
     *
     * @var \Tests\App\Models\User
     */
    private User $user;

    /**
     * The post the comments belong to.
     *
     * @var \Tests\App\Models\Post
     */
    private Post $post;

    /**
     * Create a user and a post in the past and let the comments touch the user.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::BEFORE);
        $this->user = User::factory()->create();
        $this->post = Post::factory()->create();
        Carbon::setTestNow('2026-10-25 13:45:10');
        Comment::setGlobalTouchedRelations(['user']);
    }

    /**
     * Restore the touched relations and the time.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Comment::setGlobalTouchedRelations(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * A row which the update has not found does not touch its parent.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testMissingRowDoesNotTouch(): void
    {
        // act
        Comment::query()->bulk()->uniqueBy('text')->update([
            ['text' => 'missing-' . uniqid(), 'user_id' => $this->user->id, 'post_id' => $this->post->id],
        ]);

        // assert
        self::assertSame(self::BEFORE, $this->userUpdatedAt());
    }

    /**
     * A row whose saving or updating is cancelled does not touch its parent.
     *
     * @param string $event
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cancellingEvents')]
    public function testCancelledRowDoesNotTouch(string $event): void
    {
        // arrange
        $comment = $this->comment();
        $comment->text = 'changed-' . uniqid();
        $sut = Comment::query()->bulk()->{'on' . ucfirst($event)}(fn () => false);

        // act
        $sut->update([$comment]);

        // assert
        self::assertSame(self::BEFORE, $this->userUpdatedAt());
    }

    /**
     * The events which cancel the update of a row.
     *
     * @return array<string, array{string}>
     */
    public static function cancellingEvents(): array
    {
        return ['saving' => ['saving'], 'updating' => ['updating']];
    }

    /**
     * In a mixed chunk only the parent of the updated row is touched, not the one of the
     * row without changes next to it.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testOnlyTheParentOfTheUpdatedRowIsTouched(): void
    {
        // arrange
        Carbon::setTestNow(self::BEFORE);
        $other = User::factory()->create();
        Carbon::setTestNow('2026-10-25 13:45:10');
        $changed = $this->comment();
        $changed->text = 'changed-' . uniqid();
        $clean = $this->comment();
        $clean->user_id = $other->id;
        $clean->save();
        DB::table(User::table())->whereIn('id', [$this->user->id, $other->id])->update(['updated_at' => self::BEFORE]);
        $clean = $clean->fresh() ?? $clean;
        // The relation is loaded already, as it often is: a clean row must still not touch it.
        $clean->load('user');

        // act
        Comment::query()->bulk()->update([$changed, $clean]);

        // assert
        self::assertSame('2026-10-25 13:45:10', $this->userUpdatedAt());
        self::assertSame(
            self::BEFORE,
            substr((string) DB::table(User::table())->where('id', $other->id)->value('updated_at'), 0, 19)
        );
    }

    /**
     * A row whose changes are all left out by `updateOnly()` writes nothing, so it does not touch.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowWithChangesLeftOutDoesNotTouch(): void
    {
        // arrange
        $comment = $this->comment();
        $comment->text = 'changed-' . uniqid();

        // act
        Comment::query()->bulk()->updateOnly(['post_id'])->update([$comment]);

        // assert
        self::assertSame(self::BEFORE, $this->userUpdatedAt());
    }

    /**
     * A `saved` listener which cleans the model does not stop the written row from touching.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testListenerCleaningTheModelDoesNotStopTheTouch(): void
    {
        // arrange
        $comment = $this->comment();
        $comment->text = 'changed-' . uniqid();
        $sut = Comment::query()->bulk()->onSaved(function (Comment $model): void {
            $model->syncOriginal();
        });

        // act
        $sut->update([$comment]);

        // assert
        self::assertSame('2026-10-25 13:45:10', $this->userUpdatedAt());
    }

    /**
     * A row which has been updated still touches its parent.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdatedRowTouches(): void
    {
        // arrange
        $comment = $this->comment();
        $comment->text = 'changed-' . uniqid();

        // act
        Comment::query()->bulk()->update([$comment]);

        // assert
        self::assertSame('2026-10-25 13:45:10', $this->userUpdatedAt());
    }

    /**
     * Create a comment of the user in the past.
     *
     * @return \Tests\App\Models\Comment
     */
    private function comment(): Comment
    {
        Carbon::setTestNow(self::BEFORE);
        /** @var \Tests\App\Models\Comment $comment */
        $comment = Comment::query()->create([
            'user_id' => $this->user->id,
            'post_id' => $this->post->id,
            'text' => 'comment-' . uniqid(),
        ]);
        DB::table(User::table())->where('id', $this->user->id)->update(['updated_at' => self::BEFORE]);
        Carbon::setTestNow('2026-10-25 13:45:10');

        return $comment->fresh() ?? $comment;
    }

    /**
     * Get the stored `updated_at` of the user, cut to the seconds.
     *
     * @return string
     */
    private function userUpdatedAt(): string
    {
        return substr((string) DB::table(User::table())->where('id', $this->user->id)->value('updated_at'), 0, 19);
    }
}
