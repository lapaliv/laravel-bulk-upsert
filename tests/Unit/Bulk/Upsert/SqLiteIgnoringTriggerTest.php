<?php

namespace Tests\Unit\Bulk\Upsert;

use Illuminate\Container\Container;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Exceptions\BulkRowsWereNotSaved;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * A SQLite trigger with `RAISE(IGNORE)` skips a row without an error.
 *
 * Regression test. The driver took a successful INSERT as the proof that every row had been
 * created, so a row skipped by such a trigger was taken as created by this call: `created`
 * was dispatched for it and its update was skipped.
 *
 * @internal
 */
final class SqLiteIgnoringTriggerTest extends TestCaseWrapper
{
    /**
     * Create the trigger which skips the rows named `skip-*`.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite') {
            self::markTestSkipped('RAISE(IGNORE) is a SQLite trigger feature.');
        }

        DB::table(Member::table())->delete();
        DB::unprepared('DROP TRIGGER IF EXISTS members_skip');
        DB::unprepared(
            "CREATE TRIGGER members_skip BEFORE INSERT ON members WHEN NEW.name LIKE 'skip-%' "
            . 'BEGIN SELECT RAISE(IGNORE); END'
        );
    }

    /**
     * Drop the trigger.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (DB::getDefaultConnection() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS members_skip');
        }

        parent::tearDown();
    }

    /**
     * A skipped new row is reported, and only the created row gets `created`.
     *
     * @return void
     */
    public function testSkippedNewRowIsReported(): void
    {
        // arrange
        $created = [];
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->onCreated(function (Member $member) use (&$created): void {
                $created[] = $member->email;
            });

        // act
        try {
            $sut->upsert([
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'skip-a'],
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'mine-b'],
            ]);
            $exception = null;
        } catch (BulkRowsWereNotSaved $caught) {
            $exception = $caught;
        }

        // assert
        self::assertNotNull($exception);
        self::assertSame(['a@x'], array_map(fn ($m) => $m->email, $exception->getRows()));
        self::assertSame(['b@x' => 'mine-b'], DB::table(Member::table())->pluck('name', 'email')->all());
        self::assertSame(['b@x'], $created);
    }

    /**
     * A row which another process has inserted meanwhile, and whose own insert the trigger
     * skips, is updated: the successful INSERT has not created it.
     *
     * Before, a successful SQLite INSERT was taken as the proof that every row was created, so
     * this row got `created`, its update was skipped and the input was lost.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowInsertedMeanwhileAndSkippedByTheTriggerIsUpdated(): void
    {
        // arrange
        $created = [];
        $updated = [];
        $connection = DB::connection();
        $armed = true;
        $connection->beforeExecuting(function (string $query) use (&$armed, $connection): void {
            if (!$armed || !str_starts_with(strtolower(ltrim($query)), 'insert into')) {
                return;
            }

            $armed = false;
            // Another connection, as another process; the trigger does not skip `theirs`.
            $other = (new ConnectionFactory(new Container()))->make($connection->getConfig(), 'race');
            $other->getPdo()->exec("insert into members (tenant_id, email, name) values (1, 'a@x', 'theirs')");
            $other->disconnect();
        });
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->onCreated(function (Member $member) use (&$created): void {
                $created[] = $member->email;
            })
            ->onUpdated(function (Member $member) use (&$updated): void {
                $updated[] = $member->email;
            });

        // act
        try {
            $sut->upsert([
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'skip-a'],
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'mine-b'],
            ]);
        } finally {
            $armed = false;
        }

        // assert
        self::assertSame(
            ['a@x' => 'skip-a', 'b@x' => 'mine-b'],
            DB::table(Member::table())->orderBy('email')->pluck('name', 'email')->all()
        );
        self::assertSame(['b@x'], $created);
        self::assertSame(['a@x'], $updated);
    }

    /**
     * Inside an outer transaction, the undone attempt keeps the rows written before it.
     *
     * @return void
     */
    public function testUndoneAttemptKeepsTheOuterTransaction(): void
    {
        // act
        DB::transaction(function (): void {
            DB::table(Member::table())->insert(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'outer@x', 'name' => 'outer']);

            try {
                Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])->upsert([
                    ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'skip-a'],
                    ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'mine-b'],
                ]);
            } catch (BulkRowsWereNotSaved) {
                //
            }
        });

        // assert
        self::assertSame(
            ['b@x' => 'mine-b', 'outer@x' => 'outer'],
            DB::table(Member::table())->orderBy('email')->pluck('name', 'email')->all()
        );
    }

    /**
     * A plain create does not take a skipped row as created.
     *
     * @return void
     */
    public function testCreateDoesNotTakeTheSkippedRowAsCreated(): void
    {
        // arrange
        $created = [];
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->onCreated(function (Member $member) use (&$created): void {
                $created[] = $member->email;
            });

        // act
        $sut->create([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'skip-a'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'mine-b'],
        ]);

        // assert
        self::assertSame(['b@x' => 'mine-b'], DB::table(Member::table())->pluck('name', 'email')->all());
        self::assertSame(['b@x'], $created);
    }
}
