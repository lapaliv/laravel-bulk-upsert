<?php

namespace Tests\Unit\Bulk\Upsert;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\App\Models\Comment;
use Tests\App\Models\Member;
use Tests\App\Models\Post;
use Tests\TestCaseWrapper;

/**
 * An upsert must not hide the errors of the new rows.
 *
 * Regression test. The insert part of an upsert used `INSERT IGNORE` (MySQL) and
 * `INSERT OR IGNORE` (SQLite), which suppress much more than the duplicate key the upsert
 * expects: a NOT NULL violation was skipped (SQLite) or the value was coerced (MySQL), and
 * the upsert reported success. PostgreSQL (`ON CONFLICT DO NOTHING`) raised the error.
 *
 * @internal
 */
final class UpsertDoesNotHideErrorsTest extends TestCaseWrapper
{
    /**
     * Clean the table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
    }

    /**
     * A NULL in a NOT NULL column of a new row fails the upsert and stores nothing.
     *
     * The valid row of the same statement is not stored either: the statement is atomic.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testNullInNotNullColumnFails(): void
    {
        // act
        try {
            Member::query()->bulk()->uniqueBy('email')->upsert([
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'valid@x', 'name' => 'valid'],
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'broken@x', 'name' => null],
            ]);
            $failed = false;
        } catch (QueryException) {
            $failed = true;
        }

        // assert
        $stored = DB::table(Member::table())->orderBy('email')->get(['email', 'name'])
            ->map(fn ($row) => $row->email . '=' . var_export($row->name, true))->all();
        self::assertTrue($failed, 'The upsert has to fail; stored: ' . json_encode($stored));
        self::assertSame([], $stored);
    }

    /**
     * A row which appears meanwhile is skipped wherever it is in the chunk; the new rows are stored.
     *
     * A `creating` listener inserts the conflicting row, so on MySQL the plain INSERT fails with
     * a duplicate key and the rows are inserted one by one.
     *
     * @param int $position
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('positions')]
    public function testConflictAnywhereInTheChunkKeepsTheNewRows(int $position): void
    {
        // arrange
        $emails = ['a@x', 'b@x', 'c@x'];
        $conflicting = $emails[$position];
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onCreating(function (Member $member) use ($conflicting): void {
                if ($member->email === $conflicting
                    && DB::table(Member::table())->where('email', $conflicting)->doesntExist()
                ) {
                    DB::table(Member::table())->insert(
                        ['tenant_id' => Member::CURRENT_TENANT, 'email' => $conflicting, 'name' => 'old']
                    );
                }
            });

        // act
        $result = $sut->upsertAndReturn(array_map(
            fn (string $email) => ['tenant_id' => Member::CURRENT_TENANT, 'email' => $email, 'name' => 'new'],
            $emails
        ));

        // assert
        self::assertSame($emails, DB::table(Member::table())->orderBy('email')->pluck('email')->all());
        self::assertSame(['new', 'new', 'new'], DB::table(Member::table())->orderBy('email')->pluck('name')->all());
        self::assertSame($emails, $result->pluck('email')->sort()->values()->all());
    }

    /**
     * The positions of the conflicting row in the chunk.
     *
     * @return array<string, array{int}>
     */
    public static function positions(): array
    {
        return ['first' => [0], 'middle' => [1], 'last' => [2]];
    }

    /**
     * A conflict next to an invalid row: the error reaches the caller and no new row stays.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testConflictNextToInvalidRowStoresNoNewRow(): void
    {
        // arrange
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onCreating(function (Member $member): void {
                if ($member->email === 'a@x' && DB::table(Member::table())->where('email', 'a@x')->doesntExist()) {
                    DB::table(Member::table())->insert(
                        ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'old']
                    );
                }
            });

        // act
        try {
            $sut->upsert([
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'new'],
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'valid'],
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'c@x', 'name' => null],
            ]);
            $failed = false;
        } catch (QueryException) {
            $failed = true;
        }

        // assert
        self::assertTrue($failed);
        // Only the row of the listener exists: the new valid row has been rolled back.
        self::assertSame(['a@x'], DB::table(Member::table())->pluck('email')->all());
    }

    /**
     * A failed upsert inside an outer transaction behaves as a failed plain INSERT would.
     *
     * MySQL and SQLite roll back the failed statement only, so the outer transaction keeps its
     * other changes (on MySQL the one-by-one retry runs in a nested transaction, which is
     * rolled back as a whole). PostgreSQL aborts the whole transaction on any error, as it
     * does for a plain Eloquent insert: the package does not add a savepoint of its own.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function testFailedUpsertInsideTransactionBehavesAsFailedInsert(): void
    {
        // act
        DB::transaction(function (): void {
            DB::table(Member::table())->insert(
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'outer@x', 'name' => 'outer']
            );

            try {
                Member::query()->bulk()->uniqueBy('email')
                    ->onCreating(function (Member $member): void {
                        if ($member->email === 'a@x'
                            && DB::table(Member::table())->where('email', 'a@x')->doesntExist()
                        ) {
                            DB::table(Member::table())->insert(
                                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'old']
                            );
                        }
                    })
                    ->upsert([
                        ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'new'],
                        // Stored by the one-by-one retry, then rolled back with it.
                        ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'valid'],
                        ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'c@x', 'name' => null],
                    ]);
            } catch (QueryException) {
                // expected
            }
        });

        // assert
        $expected = DB::connection()->getDriverName() === 'pgsql' ? [] : ['a@x', 'outer@x'];
        self::assertSame($expected, DB::table(Member::table())->orderBy('email')->pluck('email')->all());
        self::assertSame(0, DB::connection()->transactionLevel());
    }

    /**
     * A too long value of a new row fails the upsert instead of being cut.
     *
     * SQLite does not check the length of a string column, so the case does not exist there.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testTooLongValueFails(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            self::markTestSkipped('SQLite does not check the length of a string column.');
        }

        // act
        try {
            Member::query()->bulk()->uniqueBy('email')->upsert([
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => str_repeat('x', 300)],
            ]);
            $failed = false;
        } catch (QueryException) {
            $failed = true;
        }

        // assert
        self::assertTrue($failed, 'stored: ' . json_encode(DB::table(Member::table())->pluck('name')->all()));
        self::assertSame(0, DB::table(Member::table())->count());
    }

    /**
     * A foreign key violation of a new row fails the upsert instead of skipping the row.
     *
     * The SQLite connection of the tests does not enforce the foreign keys, so the case is
     * checked on MySQL and PostgreSQL.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForeignKeyViolationFails(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            self::markTestSkipped('The SQLite connection of the tests does not enforce the foreign keys.');
        }

        // arrange
        $text = 'upsert-fk-' . uniqid();
        $userId = (int) DB::table('users')->insertGetId(
            ['name' => 'u', 'email' => $text . '@x', 'gender' => 'male', 'posts_count' => 0, 'is_admin' => false]
        );
        $missingPostId = (int) DB::table(Post::table())->max('id') + 1000;

        // act
        try {
            Comment::query()->bulk()->uniqueBy('text')->upsert([
                ['user_id' => $userId, 'post_id' => $missingPostId, 'text' => $text],
            ]);
            $failed = false;
        } catch (QueryException) {
            $failed = true;
        }

        // assert
        self::assertTrue($failed);
        self::assertSame(0, DB::table(Comment::table())->where('text', $text)->count());
    }

    /**
     * `create(..., ignoreConflicts: true)` keeps skipping the duplicates as before.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCreateOrIgnoreStillSkipsDuplicates(): void
    {
        // arrange
        DB::table(Member::table())->insert(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'old']);

        // act
        Member::query()->bulk()->uniqueBy('email')->create([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'new'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'new'],
        ], true);

        // assert
        self::assertSame(['old', 'new'], DB::table(Member::table())->orderBy('email')->pluck('name')->all());
    }

    /**
     * A concurrent insert of the same key is still resolved: the row gets the requested values.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testConcurrentInsertOfTheSameKeyIsUpdated(): void
    {
        // arrange
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onCreating(function (Member $member): void {
                if (DB::table(Member::table())->where('email', $member->email)->doesntExist()) {
                    DB::table(Member::table())->insert(
                        ['tenant_id' => Member::CURRENT_TENANT, 'email' => $member->email, 'name' => 'old']
                    );
                }
            });

        // act
        $sut->upsert([['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'new']]);

        // assert
        self::assertSame(['new'], DB::table(Member::table())->pluck('name')->all());
    }
}
