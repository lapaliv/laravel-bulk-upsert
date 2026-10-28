<?php

namespace Tests\Unit\Bulk\Delete;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\App\Models\Comment;
use Tests\App\Models\Member;
use Tests\App\Models\Post;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;

/**
 * A member without the `updated_at` column.
 *
 * @internal
 */
class DeletedStateMemberWithoutUpdatedAt extends Member
{
    /**
     * The update time column does not exist.
     *
     * @var string|null
     */
    public const UPDATED_AT = null;
}

/**
 * The deleted models describe the stored rows, whether there are listeners or not.
 *
 * Regression test. The state of the models was changed by the step which dispatched the
 * `deleted` events, and that step returned at once without listeners: a soft-deleted model
 * got no `deleted_at`, a removed model kept `exists = true` in any case, and a soft delete
 * did not touch `updated_at`, unlike `Model::delete()`.
 *
 * @internal
 */
final class DeletedModelStateTest extends TestCaseWrapper
{
    /**
     * The frozen time of the deletion.
     *
     * @var string
     */
    private const string NOW = '2026-10-25 13:45:10';

    /**
     * Store one member in the past and freeze the clock.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert([
            'tenant_id' => Member::CURRENT_TENANT,
            'email' => 'a@x',
            'name' => 'old',
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
        ]);
        Carbon::setTestNow(self::NOW);
    }

    /**
     * A soft delete fills `deleted_at` and `updated_at` in the row and in the model.
     *
     * @param bool $withListener
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('listeners')]
    public function testSoftDelete(bool $withListener): void
    {
        // arrange
        $model = Member::query()->firstOrFail();
        $model->name = 'dirty, not deleted';
        $sut = Member::query()->bulk();

        if ($withListener) {
            $sut->onDeleted(fn () => null);
        }

        // act
        $sut->delete([$model]);

        // assert
        $row = DB::table(Member::table())->first();
        self::assertNotNull($row);
        self::assertSame(self::NOW, substr((string) $row->deleted_at, 0, 19));
        self::assertSame(self::NOW, substr((string) $row->updated_at, 0, 19));
        self::assertSame('old', $row->name);
        self::assertTrue($model->exists);
        self::assertSame(self::NOW, $model->deleted_at?->toDateTimeString());
        self::assertSame(self::NOW, $model->updated_at?->toDateTimeString());
        self::assertTrue($model->trashed());
        self::assertFalse($model->isDirty('deleted_at'));
        self::assertFalse($model->isDirty('updated_at'));
        // A change which the statement has not written stays a change.
        self::assertTrue($model->isDirty('name'));
    }

    /**
     * With `UPDATED_AT = null` the soft delete writes `deleted_at` only (the column is still
     * in the table here, so it shows that the library does not write it).
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSoftDeleteWithoutUpdatedAt(): void
    {
        // arrange
        $model = DeletedStateMemberWithoutUpdatedAt::query()->firstOrFail();

        // act
        DeletedStateMemberWithoutUpdatedAt::query()->bulk()->delete([$model]);

        // assert
        $row = DB::table(Member::table())->first();
        self::assertNotNull($row);
        self::assertSame(self::NOW, substr((string) $row->deleted_at, 0, 19));
        self::assertSame('2020-01-01 00:00:00', substr((string) $row->updated_at, 0, 19));
        self::assertTrue($model->trashed());
    }

    /**
     * A forced delete removes the row and the model does not exist any more.
     *
     * @param bool $withListener
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('listeners')]
    public function testForceDelete(bool $withListener): void
    {
        // arrange
        $model = Member::query()->firstOrFail();
        $sut = Member::query()->bulk();

        if ($withListener) {
            $sut->onForceDeleted(fn () => null);
        }

        // act
        $sut->forceDelete([$model]);

        // assert
        self::assertSame(0, DB::table(Member::table())->count());
        self::assertFalse($model->exists);
        self::assertNull($model->deleted_at);
    }

    /**
     * A model without soft deletes does not exist after the delete.
     *
     * @param bool $withListener
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('listeners')]
    public function testHardDelete(bool $withListener): void
    {
        // arrange
        $comment = $this->comment();
        $sut = Comment::query()->bulk();

        if ($withListener) {
            $sut->onDeleted(fn () => null);
        }

        // act
        $sut->delete([$comment]);

        // assert
        self::assertSame(0, DB::table(Comment::table())->where('id', $comment->id)->count());
        self::assertFalse($comment->exists);
    }

    /**
     * The `deleted` listener sees the state of the deleted model already.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testListenerSeesTheFinalState(): void
    {
        // arrange
        $seen = [];
        $comment = $this->comment();
        $member = Member::query()->firstOrFail();

        // act
        Comment::query()->bulk()->onDeleted(function (Comment $model) use (&$seen): void {
            $seen['comment exists'] = $model->exists;
        })->delete([$comment]);
        Member::query()->bulk()->onDeleted(function (Member $model) use (&$seen): void {
            $seen['member trashed'] = $model->trashed();
            $seen['member clean'] = !$model->isDirty('deleted_at');
        })->delete([$member]);

        // assert
        self::assertSame(['comment exists' => false, 'member trashed' => true, 'member clean' => true], $seen);
    }

    /**
     * A row cancelled by `deleting` keeps its model as it was.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCancelledRowKeepsItsModel(): void
    {
        // arrange
        $model = Member::query()->firstOrFail();

        // act
        Member::query()->bulk()->onDeleting(fn () => false)->delete([$model]);

        // assert
        self::assertNull($model->deleted_at);
        self::assertSame('2020-01-01 00:00:00', $model->updated_at?->toDateTimeString());
        self::assertNull(DB::table(Member::table())->value('deleted_at'));
    }

    /**
     * With and without a listener.
     *
     * @return array<string, array{bool}>
     */
    public static function listeners(): array
    {
        return ['without listeners' => [false], 'with a listener' => [true]];
    }

    /**
     * Create a comment of a new user and post.
     *
     * @return \Tests\App\Models\Comment
     */
    private function comment(): Comment
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        /** @var \Tests\App\Models\Comment */
        return Comment::query()->create(['user_id' => $user->id, 'post_id' => $post->id, 'text' => 'c-' . uniqid()]);
    }
}
