<?php

namespace Tests\Unit\Bulk;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\App\Models\Member;
use Tests\App\Models\Post;
use Tests\TestCaseWrapper;

/**
 * The failure which the tests inject into the listeners.
 *
 * @internal
 */
final class InjectedFailure extends RuntimeException
{
    //
}

/**
 * What stays in the storage after a flush which has thrown.
 *
 * Regression test. A failed flush either wrote rows again or lost them: `*OrAccumulate()`
 * kept the whole accumulation until every chunk had run, so a chunk which had already been
 * written was written once more by the next flush; a forced flush removed the accumulation
 * before the first chunk, so the chunks which had not run yet were lost.
 *
 * The contract now: the rows whose operation has been completed (inserted, updated, deleted)
 * leave the storage, the other rows stay and are retried by the next flush. An upsert row is
 * completed only when this call has created it or has updated it.
 *
 * The posts have no unique key, so a row written twice is visible as a duplicate.
 *
 * @internal
 */
final class AccumulationFailureTest extends TestCaseWrapper
{
    /**
     * The prefix of the texts of the posts which the tests write.
     *
     * @var string
     */
    private const string PREFIX = 'accumulation-';

    /**
     * Remove the own posts and the members.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Post::table())->where('text', 'like', self::PREFIX . '%')->delete();
        DB::table(Member::table())->delete();
    }

    /**
     * An automatic flush which fails before the second INSERT does not repeat the first chunk.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testAutomaticFlushDoesNotRepeatTheFirstChunk(): void
    {
        // arrange
        $failures = 1;
        $sut = Post::query()->bulk()->chunk(2)->onCreating($this->failOn('3', $failures));

        // act
        $this->expectInjectedFailure(fn () => $sut->createOrAccumulate($this->rows(4)));
        $afterFailure = $this->texts();
        $sut->saveAccumulated();
        $sut->saveAccumulated();

        // assert
        self::assertSame($this->texts(2), $afterFailure);
        self::assertSame($this->texts(4), $this->texts());
    }

    /**
     * A forced flush which fails does not lose the chunks which have not run yet.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForcedFlushDoesNotLoseTheNextChunks(): void
    {
        // arrange
        $failures = 1;
        $sut = Post::query()->bulk()->chunk(100)->onCreating($this->failOn('3', $failures));
        $sut->createOrAccumulate($this->rows(6));
        $sut->chunk(2);

        // act
        $this->expectInjectedFailure(fn () => $sut->saveAccumulated());
        $afterFailure = $this->texts();
        $sut->saveAccumulated();
        $sut->saveAccumulated();

        // assert
        self::assertSame($this->texts(2), $afterFailure);
        self::assertSame($this->texts(6), $this->texts());
    }

    /**
     * A chunk which fails before its INSERT several times stays until it succeeds.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChunkWhichFailsTwiceIsKept(): void
    {
        // arrange
        $failures = 2;
        $sut = Post::query()->bulk()->chunk(2)->onCreating($this->failOn('1', $failures));
        $sut->createOrAccumulate($this->rows(1));

        // act
        $this->expectInjectedFailure(fn () => $sut->saveAccumulated());
        $this->expectInjectedFailure(fn () => $sut->saveAccumulated());
        $afterFailures = $this->texts();
        $sut->saveAccumulated();

        // assert
        self::assertSame([], $afterFailures);
        self::assertSame($this->texts(1), $this->texts());
    }

    /**
     * A row cancelled by a listener does not block the accumulation.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCancelledRowDoesNotBlockTheAccumulation(): void
    {
        // arrange
        $sut = Post::query()->bulk()->chunk(2)
            ->onCreating(fn (Post $post) => $post->text === self::PREFIX . '2' ? false : null);

        // act
        $sut->createOrAccumulate($this->rows(3));
        $sut->saveAccumulated();
        $sut->saveAccumulated();

        // assert
        self::assertSame([self::PREFIX . '1', self::PREFIX . '3'], $this->texts());
    }

    /**
     * A chunk which fails after its INSERT is not inserted again; the next chunks still are.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChunkFailedAfterTheInsertIsNotInsertedAgain(): void
    {
        // arrange
        $failures = 1;
        $sut = Post::query()->bulk()->uniqueBy('text')->chunk(100)->onCreated($this->failOn('3', $failures));
        $sut->createOrAccumulate($this->rows(5));
        $sut->chunk(2);

        // act
        $this->expectInjectedFailure(fn () => $sut->saveAccumulated());
        $afterFailure = $this->texts();
        $sut->saveAccumulated();
        $sut->saveAccumulated();

        // assert
        self::assertSame($this->texts(4), $afterFailure);
        self::assertSame($this->texts(5), $this->texts());
    }

    /**
     * A chunk rejected by the database is kept, so it is written once the input is fixed.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChunkRejectedByTheDatabaseIsKept(): void
    {
        // arrange
        $broken = new Post();
        $sut = Post::query()->bulk()->chunk(100);
        $sut->createOrAccumulate([['text' => self::PREFIX . '1'], ['text' => self::PREFIX . '2']]);
        $sut->createOrAccumulate([$broken, ['text' => self::PREFIX . '4']]);
        $sut->chunk(2);

        // act
        try {
            $sut->saveAccumulated();
            self::fail('The post without a text has to be rejected by the database.');
        } catch (QueryException) {
            // expected
        }

        $afterFailure = $this->texts();
        $broken->text = self::PREFIX . '3';
        $sut->saveAccumulated();

        // assert
        self::assertSame($this->texts(2), $afterFailure);
        self::assertSame($this->texts(4), $this->texts());
    }

    /**
     * A chunk which fails after its UPDATE is not updated again.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChunkFailedAfterTheUpdateIsNotUpdatedAgain(): void
    {
        // arrange
        $this->members(['a', 'b', 'c', 'd']);
        $updating = [];
        $failures = 1;
        $sut = Member::query()->bulk()->uniqueBy('email')->chunk(100)
            ->onUpdating(function (Member $member) use (&$updating): void {
                $updating[] = $member->email;
            })
            ->onUpdated($this->failOnMember('c@x', $failures));
        $sut->updateOrAccumulate($this->changes(['a', 'b', 'c', 'd']));
        $sut->chunk(2);

        // act
        $this->expectInjectedFailure(fn () => $sut->updateAccumulated());
        $sut->updateAccumulated();

        // assert
        self::assertSame(['a@x', 'b@x', 'c@x', 'd@x'], $updating);
        self::assertSame(['new', 'new', 'new', 'new'], $this->names());
    }

    /**
     * A chunk which fails before its UPDATE is kept and updated on the retry.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChunkFailedBeforeTheUpdateIsRetried(): void
    {
        // arrange
        $this->members(['a', 'b', 'c', 'd']);
        $failures = 1;
        $sut = Member::query()->bulk()->uniqueBy('email')->chunk(100)
            ->onUpdating($this->failOnMember('c@x', $failures));
        $sut->updateOrAccumulate($this->changes(['a', 'b', 'c', 'd']));
        $sut->chunk(2);

        // act
        $this->expectInjectedFailure(fn () => $sut->updateAccumulated());
        $afterFailure = $this->names();
        $sut->updateAccumulated();

        // assert
        self::assertSame(['new', 'new', 'old', 'old'], $afterFailure);
        self::assertSame(['new', 'new', 'new', 'new'], $this->names());
    }

    /**
     * A chunk which fails after its soft delete is not deleted again.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChunkFailedAfterTheDeleteIsNotDeletedAgain(): void
    {
        // arrange
        $this->members(['a', 'b', 'c', 'd']);
        $deleting = [];
        $failures = 1;
        $sut = Member::query()->bulk()->uniqueBy('email')->chunk(100)
            ->onDeleting(function (Member $member) use (&$deleting): void {
                $deleting[] = $member->email;
            })
            ->onDeleted($this->failOnMember('c@x', $failures));
        $sut->deleteOrAccumulate([['email' => 'a@x'], ['email' => 'b@x'], ['email' => 'c@x'], ['email' => 'd@x']]);
        $sut->chunk(2);

        // act
        $this->expectInjectedFailure(fn () => $sut->deleteAccumulated());
        $sut->deleteAccumulated();

        // assert
        self::assertSame(['a@x', 'b@x', 'c@x', 'd@x'], $deleting);
        self::assertSame(4, DB::table(Member::table())->whereNotNull('deleted_at')->count());
    }

    /**
     * An upsert whose update fails after the insert of the new rows is retried for the
     * existing rows only.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpsertWhoseUpdateFailsIsRetriedWithoutDuplicates(): void
    {
        // arrange
        $this->members(['a']);
        $failures = 1;
        $sut = Member::query()->bulk()->uniqueBy('email')->chunk(10)
            ->onUpdating($this->failOnMember('a@x', $failures));
        $sut->upsertOrAccumulate($this->upsertRows(['a', 'b']));

        // act
        $this->expectInjectedFailure(fn () => $sut->upsertAccumulated());
        $afterFailure = $this->names();
        $sut->upsertAccumulated();

        // assert
        self::assertSame(['old', 'new'], $afterFailure);
        self::assertSame(['a@x', 'b@x'], DB::table(Member::table())->orderBy('email')->pluck('email')->all());
        self::assertSame(['new', 'new'], $this->names());
    }

    /**
     * A row whose upsert insert has been ignored still needs the update, also after a failure.
     *
     * A listener inserts the same row first, so the insert of the package is ignored and the
     * row goes to the update. The failure is injected before the update and, in the second
     * case, into the created events, before the package restores the requested attributes.
     *
     * @param string $failingEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('raceFailures')]
    public function testRowWhichLostTheRaceIsUpdatedOnTheRetry(string $failingEvent): void
    {
        // arrange
        $failures = 1;
        $fail = function () use (&$failures): void {
            if ($failures > 0) {
                $failures--;

                throw new InjectedFailure('The ' . $failures . ' event failed.');
            }
        };
        $sut = Member::query()->bulk()->uniqueBy('email')->chunk(10)
            ->onCreating(function (Member $member): void {
                if (DB::table(Member::table())->where('email', $member->email)->doesntExist()) {
                    DB::table(Member::table())->insert(
                        ['tenant_id' => Member::CURRENT_TENANT, 'email' => $member->email, 'name' => 'old']
                    );
                }
            });
        $failingEvent === 'updating' ? $sut->onUpdating($fail) : $sut->onSavedMany($fail);
        $sut->upsertOrAccumulate($this->upsertRows(['a']));

        // act
        $this->expectInjectedFailure(fn () => $sut->upsertAccumulated());
        $sut->upsertAccumulated();

        // assert
        self::assertSame(1, DB::table(Member::table())->count());
        self::assertSame(['new'], $this->names());
    }

    /**
     * The events in which the race tests fail.
     *
     * @return array<string, array{string}>
     */
    public static function raceFailures(): array
    {
        return ['before the update' => ['updating'], 'in the saved events' => ['savedMany']];
    }

    /**
     * A forced flush started by a listener during a flush of the same storage is rejected.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testNestedForcedFlushIsRejectedWithoutDuplicates(): void
    {
        // arrange
        $nested = null;
        $sut = Post::query()->bulk()->chunk(100);
        $sut->onCreating(function (Post $post) use ($sut, &$nested): void {
            if ($post->text === self::PREFIX . '1' && $nested === null) {
                try {
                    $sut->createAccumulated();
                    $nested = 'ran';
                } catch (LogicException) {
                    $nested = 'rejected';
                }
            }
        });
        $sut->createOrAccumulate($this->rows(4));
        $sut->chunk(2);

        // act
        $sut->createAccumulated();

        // assert
        self::assertSame('rejected', $nested);
        self::assertSame($this->texts(4), $this->texts());
    }

    /**
     * A row which a listener adds during a flush is not lost, and is written by the next flush.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowAddedByListenerIsNotLost(): void
    {
        // arrange
        $added = false;
        $sut = Post::query()->bulk()->chunk(10);
        $sut->onCreating(function () use ($sut, &$added): void {
            if (!$added) {
                $added = true;
                $sut->createOrAccumulate([['text' => self::PREFIX . '3']]);
            }
        });
        $sut->createOrAccumulate($this->rows(2));

        // act
        $sut->createAccumulated();
        $afterFirst = $this->texts();
        $sut->createAccumulated();

        // assert
        self::assertSame($this->texts(2), $afterFirst);
        self::assertSame($this->texts(3), $this->texts());
    }

    /**
     * The listeners take their decisions again for a retried row, as for a second `save()`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testListenersDecideAgainForRetriedRow(): void
    {
        // arrange
        $calls = [];
        $failures = 1;
        $sut = Post::query()->bulk()->chunk(10)
            ->onCreating(function (Post $post) use (&$calls, &$failures) {
                $calls[] = $post->text;

                if ($post->text === self::PREFIX . '1') {
                    return false;
                }

                if ($post->text === self::PREFIX . '2' && $failures > 0) {
                    $failures--;

                    throw new InjectedFailure('The row 2 failed.');
                }

                return null;
            });
        $sut->createOrAccumulate($this->rows(2));

        // act
        $this->expectInjectedFailure(fn () => $sut->createAccumulated());
        $sut->createAccumulated();

        // assert
        self::assertSame([self::PREFIX . '2'], $this->texts());
        self::assertSame(
            [self::PREFIX . '1', self::PREFIX . '2', self::PREFIX . '1', self::PREFIX . '2'],
            $calls
        );
    }

    /**
     * The changes which a listener has made to a retried row are not undone.
     *
     * The listener appends `!` and fails once, so the retried row gets `!` twice, as for a
     * second `Model::save()` of the same model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChangesOfListenersAreNotUndoneOnTheRetry(): void
    {
        // arrange
        $failures = 1;
        $sut = Post::query()->bulk()->chunk(10)
            ->onCreating(function (Post $post) use (&$failures): void {
                $post->text .= '!';

                if ($failures > 0) {
                    $failures--;

                    throw new InjectedFailure('The row failed.');
                }
            });
        $sut->createOrAccumulate($this->rows(1));

        // act
        $this->expectInjectedFailure(fn () => $sut->createAccumulated());
        $sut->createAccumulated();

        // assert
        self::assertSame([self::PREFIX . '1!!'], $this->texts());
    }

    /**
     * The storage is not locked after a flush which has thrown.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testFlushIsNotLockedAfterFailure(): void
    {
        // arrange
        $failures = 1;
        $sut = Post::query()->bulk()->chunk(10)->onCreating($this->failOn('1', $failures));
        $sut->createOrAccumulate($this->rows(1));
        $this->expectInjectedFailure(fn () => $sut->createAccumulated());

        // act
        // `create()` flushes the whole storage: the kept row first, then the new one.
        $sut->create([['text' => self::PREFIX . '2']]);
        $afterCreate = $this->texts();
        $sut->createAccumulated();

        // assert
        self::assertSame($this->texts(2), $afterCreate);
        self::assertSame($this->texts(2), $this->texts());
    }

    /**
     * After a failed automatic flush, new rows and the next automatic flush write every row once.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testAutomaticFlushAfterFailureWritesEveryRowOnce(): void
    {
        // arrange
        $failures = 1;
        // The first chunk is written, the second one fails before its INSERT and is kept.
        $sut = Post::query()->bulk()->chunk(2)->onCreating($this->failOn('3', $failures));
        $this->expectInjectedFailure(fn () => $sut->createOrAccumulate($this->rows(4)));

        // act
        // The kept chunk is full, so adding a row flushes it automatically; the new row waits.
        $sut->createOrAccumulate([['text' => self::PREFIX . '5']]);
        $afterAutomatic = $this->texts();
        $sut->createAccumulated();

        // assert
        self::assertSame($this->texts(4), $afterAutomatic);
        self::assertSame($this->texts(5), $this->texts());
    }

    /**
     * A chunk which fails after its force delete is not deleted again; the next chunks are.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChunkFailedAfterTheForceDeleteIsNotDeletedAgain(): void
    {
        // arrange
        $this->members(['a', 'b', 'c', 'd']);
        $failures = 1;
        $sut = Member::query()->bulk()->uniqueBy('email')->chunk(100);
        $fail = $this->failOnMember('a@x', $failures);
        $sut->onForceDeleted($fail)->onDeleted($fail);
        $sut->forceDeleteOrAccumulate([['email' => 'a@x'], ['email' => 'b@x'], ['email' => 'c@x'], ['email' => 'd@x']]);
        $sut->chunk(2);

        // act
        $this->expectInjectedFailure(fn () => $sut->forceDeleteAccumulated());
        $afterFailure = DB::table(Member::withTrashed()->getModel()->getTable())->orderBy('email')->pluck('email')->all();
        $sut->forceDeleteAccumulated();

        // assert
        self::assertSame(['c@x', 'd@x'], $afterFailure);
        self::assertSame(0, DB::table(Member::table())->count());
    }

    /**
     * An outer transaction which rolls back is retried with a new bulk instance.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function testRolledBackTransactionIsRetriedWithNewBulk(): void
    {
        // arrange
        $failures = 1;
        $attempt = function () use (&$failures): void {
            $bulk = Post::query()->bulk()->uniqueBy('text')->chunk(2)->onCreated($this->failOn('3', $failures));
            $bulk->createOrAccumulate($this->rows(4));
            $bulk->createAccumulated();
        };

        // act
        $this->expectInjectedFailure(fn () => DB::transaction($attempt));
        $afterRollback = $this->texts();
        DB::transaction($attempt);

        // assert
        self::assertSame([], $afterRollback);
        self::assertSame($this->texts(4), $this->texts());
    }

    /**
     * A row which an upsert has created leaves the storage, even when the update of another
     * row of its chunk fails.
     *
     * The `created` listener makes the insert read the rows back, so the creation is known.
     * A retried created row would go through the `updating` listener, which marks it.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowCreatedByUpsertIsNotUpdatedOnTheRetry(): void
    {
        // arrange
        $this->members(['a']);
        $updating = [];
        $failures = 1;
        $sut = Member::query()->bulk()->uniqueBy('email')->chunk(10)
            ->onCreated(fn () => null)
            ->onUpdating(function (Member $member) use (&$updating, &$failures): void {
                $updating[] = $member->email;

                if ($member->email === 'b@x') {
                    $member->name = 'unexpected retry';
                }

                if ($member->email === 'a@x' && $failures > 0) {
                    $failures--;

                    throw new InjectedFailure('The update of a failed.');
                }
            });
        $sut->upsertOrAccumulate($this->upsertRows(['a', 'b']));

        // act
        $this->expectInjectedFailure(fn () => $sut->upsertAccumulated());
        $afterFailure = $this->names();
        $sut->upsertAccumulated();

        // assert
        self::assertSame(['old', 'new'], $afterFailure);
        self::assertSame(['new', 'new'], $this->names());
        self::assertSame(['a@x', 'a@x'], $updating);
    }

    /**
     * A stale `wasRecentlyCreated` of the input model must not make an upsert skip its update.
     *
     * A listener inserts the same row first, so the insert of the package is ignored. The
     * input model still says it was created by an earlier operation.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testStaleWasRecentlyCreatedDoesNotSkipTheUpdate(): void
    {
        // arrange
        $member = new Member(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'new']);
        $member->wasRecentlyCreated = true;
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onCreating(function (Member $model): void {
                if (DB::table(Member::table())->where('email', $model->email)->doesntExist()) {
                    DB::table(Member::table())->insert(
                        ['tenant_id' => Member::CURRENT_TENANT, 'email' => $model->email, 'name' => 'old']
                    );
                }
            });

        // act
        $sut->upsert([$member]);

        // assert
        self::assertSame(['new'], $this->names());
    }

    /**
     * A change which `creating` makes to a new upsert row is stored, without any failure.
     *
     * Regression test. Without `created` listeners the insert is quiet, and the package took
     * the new row for one which had lost the race: it put back the attributes from before the
     * listeners and updated the row with them.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChangeOfCreatingListenerIsStoredByUpsert(): void
    {
        // arrange
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onCreating(function (Member $member): void {
                $member->name .= '!';
            });

        // act
        $sut->upsert($this->upsertRows(['b']));

        // assert
        self::assertSame(['new!'], $this->names());
    }

    /**
     * An upsert row changed by `creating` and failing after its INSERT keeps the change.
     *
     * The created event reads the row back, so its model is replaced; the retry must not put
     * back the attributes which the row had before the listeners.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpsertChangedByListenerAndFailedAfterTheInsertKeepsTheChange(): void
    {
        // arrange
        $failures = 1;
        $sut = Member::query()->bulk()->uniqueBy('email')->chunk(10)
            ->onCreating(function (Member $member): void {
                $member->name .= '!';
            })
            ->onCreated($this->failOnMember('b@x', $failures));
        $sut->upsertOrAccumulate($this->upsertRows(['b']));

        // act
        $this->expectInjectedFailure(fn () => $sut->upsertAccumulated());
        $sut->upsertAccumulated();

        // assert
        self::assertSame(1, DB::table(Member::table())->count());
        self::assertSame(['new!'], $this->names());
    }

    /**
     * An upsert row changed by `creating` which then fails is created with both changes.
     *
     * The listener runs again on the retry, as for a second `Model::save()`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpsertChangedByListenerAndFailedBeforeTheInsertRunsTheListenerAgain(): void
    {
        // arrange
        $failures = 1;
        $sut = Member::query()->bulk()->uniqueBy('email')->chunk(10)
            ->onCreating(function (Member $member) use (&$failures): void {
                $member->name .= '!';

                if ($failures > 0) {
                    $failures--;

                    throw new InjectedFailure('The creating event failed.');
                }
            });
        $sut->upsertOrAccumulate($this->upsertRows(['b']));

        // act
        $this->expectInjectedFailure(fn () => $sut->upsertAccumulated());
        $sut->upsertAccumulated();

        // assert
        self::assertSame(['new!!'], $this->names());
    }

    /**
     * A row which a listener adds to another group of the same storage waits for the next flush.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowAddedToAnotherGroupWaitsForTheNextFlush(): void
    {
        // arrange
        $this->members(['a', 'b', 'c']);
        $idOfA = (int)DB::table(Member::table())->where('email', 'a@x')->value('id');
        $added = false;
        $sut = Member::query()->bulk()->uniqueBy([['id'], ['email']])->chunk(10);
        $sut->onUpdating(function (Member $member) use ($sut, &$added): void {
            if (!$added && $member->email === 'a@x') {
                $added = true;
                $sut->updateOrAccumulate([['email' => 'c@x', 'name' => 'new']]);
            }
        });
        // `a` goes to the group by `id`, `b` to the group by `email`.
        $sut->updateOrAccumulate([['id' => $idOfA, 'name' => 'new'], ['email' => 'b@x', 'name' => 'new']]);

        // act
        $sut->updateAccumulated();
        $afterFirst = $this->names();
        $sut->updateAccumulated();

        // assert
        self::assertSame(['new', 'new', 'old'], $afterFirst);
        self::assertSame(['new', 'new', 'new'], $this->names());
    }

    /**
     * A nested `create()` is rejected before it adds its rows.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testNestedCreateIsRejectedWithoutAddingRows(): void
    {
        // arrange
        $rejected = false;
        $sut = Post::query()->bulk()->chunk(10);
        $sut->onCreating(function (Post $post) use ($sut, &$rejected): void {
            if ($post->text === self::PREFIX . '1') {
                try {
                    $sut->create([['text' => self::PREFIX . 'nested']]);
                } catch (LogicException) {
                    $rejected = true;
                }
            }
        });
        $sut->createOrAccumulate($this->rows(1));

        // act
        $sut->createAccumulated();
        $sut->createAccumulated();

        // assert
        self::assertTrue($rejected);
        self::assertSame($this->texts(1), $this->texts());
    }

    /**
     * Run the callback and require the injected failure.
     *
     * @param \Closure(): mixed $callback
     *
     * @return void
     */
    private function expectInjectedFailure(\Closure $callback): void
    {
        try {
            $callback();
        } catch (InjectedFailure) {
            return;
        }

        self::fail('The injected failure has not been thrown.');
    }

    /**
     * Build a listener which throws on the post with the given suffix the given number of times.
     *
     * @param string $suffix
     * @param int $failures
     *
     * @return \Closure
     */
    private function failOn(string $suffix, int &$failures): \Closure
    {
        return function (Post $post) use ($suffix, &$failures): void {
            if ($post->text === self::PREFIX . $suffix && $failures > 0) {
                $failures--;

                throw new InjectedFailure('The row ' . $suffix . ' failed.');
            }
        };
    }

    /**
     * Build a listener which throws on the member with the given email the given number of times.
     *
     * @param string $email
     * @param int $failures
     *
     * @return \Closure
     */
    private function failOnMember(string $email, int &$failures): \Closure
    {
        return function (Member $member) use ($email, &$failures): void {
            if ($member->email === $email && $failures > 0) {
                $failures--;

                throw new InjectedFailure('The member ' . $email . ' failed.');
            }
        };
    }

    /**
     * Build the input rows `accumulation-1` .. `accumulation-COUNT`.
     *
     * @param int $count
     *
     * @return array<int, array<string, string>>
     */
    private function rows(int $count): array
    {
        return array_map(fn (int $i) => ['text' => self::PREFIX . $i], range(1, $count));
    }

    /**
     * Get the texts of the own posts, or the expected texts `accumulation-1` .. `accumulation-N`.
     *
     * @param int|null $count
     *
     * @return string[]
     */
    private function texts(?int $count = null): array
    {
        if ($count !== null) {
            return array_map(fn (int $i) => self::PREFIX . $i, range(1, $count));
        }

        return DB::table(Post::table())
            ->where('text', 'like', self::PREFIX . '%')
            ->orderBy('id')
            ->pluck('text')
            ->all();
    }

    /**
     * Put members with the given names (`a` becomes `a@x`) into the table.
     *
     * @param string[] $names
     *
     * @return void
     */
    private function members(array $names): void
    {
        DB::table(Member::table())->insert(array_map(
            fn (string $name) => ['tenant_id' => Member::CURRENT_TENANT, 'email' => $name . '@x', 'name' => 'old'],
            $names
        ));
    }

    /**
     * Build the update rows which rename the given members to `new`.
     *
     * @param string[] $names
     *
     * @return array<int, array<string, string>>
     */
    private function changes(array $names): array
    {
        return array_map(fn (string $name) => ['email' => $name . '@x', 'name' => 'new'], $names);
    }

    /**
     * Build the upsert rows of the current tenant named `new`.
     *
     * @param string[] $names
     *
     * @return array<int, array<string, mixed>>
     */
    private function upsertRows(array $names): array
    {
        return array_map(
            fn (string $name) => ['tenant_id' => Member::CURRENT_TENANT, 'email' => $name . '@x', 'name' => 'new'],
            $names
        );
    }

    /**
     * Get the names of the members ordered by the email.
     *
     * @return string[]
     */
    private function names(): array
    {
        return DB::table(Member::table())->orderBy('email')->pluck('name')->all();
    }
}
