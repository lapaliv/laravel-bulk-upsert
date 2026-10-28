<?php

namespace Tests\Unit\Bulk\Upsert;

use Illuminate\Container\Container;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * A row which another process inserts right before the insert of the upsert is updated.
 *
 * Regression test. MySQL and SQLite decided which rows the insert had created by comparing
 * the primary keys with `MAX(id)` taken before the insert. A row inserted by somebody else
 * between that query and the insert has a greater key too, so it was taken as created by
 * this call: its update was skipped, the input was lost and `created` was dispatched.
 *
 * The interleaving is reproduced deterministically: right before the first INSERT of the bulk
 * a hook inserts the conflicting row through a second connection, which commits it on its own.
 * It is not a load test.
 *
 * @internal
 */
final class UpsertRaceTest extends TestCaseWrapper
{
    /**
     * Whether the hook has to insert the conflicting row before the next INSERT.
     *
     * @var bool
     */
    private static bool $armed = false;

    /**
     * The connection objects the hook has been registered on (it cannot be removed).
     *
     * @var \WeakMap<\Illuminate\Database\Connection, true>|null
     */
    private static ?\WeakMap $hooked = null;

    /**
     * Clear the table and register the hook once per connection.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
        /** @var \Illuminate\Database\Connection $connection */
        $connection = DB::connection();
        self::$hooked ??= new \WeakMap();

        if (!isset(self::$hooked[$connection])) {
            self::$hooked[$connection] = true;
            $connection->beforeExecuting(function (string $query) use ($connection): void {
                if (!self::$armed || !str_starts_with(strtolower(ltrim($query)), 'insert into')) {
                    return;
                }

                self::$armed = false;
                // Another connection, as another process: its row is committed on its own and
                // does not depend on the transactions of the bulk's connection.
                $other = (new ConnectionFactory(new Container()))->make($connection->getConfig(), 'race');
                $other->getPdo()->exec(
                    "insert into members (tenant_id, email, name) values (1, 'a@x', 'theirs')"
                );
                $other->disconnect();
            });
        }
    }

    /**
     * Disarm the hook whatever has happened.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        self::$armed = false;

        parent::tearDown();
    }

    /**
     * The input wins, and only the row of this call gets `created`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowInsertedMeanwhileIsUpdated(): void
    {
        // arrange
        $created = [];
        $updated = [];
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->onCreated(function (Member $member) use (&$created): void {
                $created[] = $member->email;
            })
            ->onUpdated(function (Member $member) use (&$updated): void {
                $updated[] = $member->email;
            });

        // act
        self::$armed = true;
        $sut->upsert($this->rows());

        // assert
        self::assertFalse(self::$armed, 'The hook has not fired.');
        self::assertSame(['a@x' => 'mine-a', 'b@x' => 'mine-b'], $this->stored());
        self::assertSame(['b@x'], $created);
        self::assertSame(['a@x'], $updated);
    }

    /**
     * The same result without any listener.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowInsertedMeanwhileIsUpdatedWithoutListeners(): void
    {
        // act
        self::$armed = true;
        Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])->upsert($this->rows());

        // assert
        self::assertFalse(self::$armed, 'The hook has not fired.');
        self::assertSame(['a@x' => 'mine-a', 'b@x' => 'mine-b'], $this->stored());
    }

    /**
     * The same result, and the returned models describe what is stored.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowInsertedMeanwhileIsReturnedUpdated(): void
    {
        // act
        self::$armed = true;
        $result = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])->upsertAndReturn($this->rows());

        // assert
        self::assertFalse(self::$armed, 'The hook has not fired.');
        self::assertSame(['a@x' => 'mine-a', 'b@x' => 'mine-b'], $this->stored());
        self::assertSame(
            ['a@x' => 'mine-a', 'b@x' => 'mine-b'],
            $result->sortBy('email')->pluck('name', 'email')->all()
        );
        self::assertSame(
            ['a@x' => false, 'b@x' => true],
            $result->sortBy('email')->mapWithKeys(fn (Member $m) => [$m->email => $m->wasRecentlyCreated])->all()
        );
    }

    /**
     * A row cancelled by `creating` before the others keeps the positions of the rest right.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCancelledRowBeforeTheOthersKeepsThePositions(): void
    {
        // arrange
        $created = [];
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->onCreating(fn (Member $member) => $member->email === '0@x' ? false : null)
            ->onCreated(function (Member $member) use (&$created): void {
                $created[] = $member->email;
            });

        // act
        self::$armed = true;
        $sut->upsert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => '0@x', 'name' => 'cancelled'],
            ...$this->rows(),
        ]);

        // assert
        self::assertFalse(self::$armed, 'The hook has not fired.');
        self::assertSame(['a@x' => 'mine-a', 'b@x' => 'mine-b'], $this->stored());
        self::assertSame(['b@x'], $created);
    }

    /**
     * A `created` listener which clears `wasRecentlyCreated` does not send the created row to
     * the update: the library keeps the driver's answer.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testListenerClearingWasRecentlyCreatedDoesNotUpdateTheCreatedRow(): void
    {
        // arrange
        $updated = [];
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->onCreated(function (Member $member): void {
                $member->wasRecentlyCreated = false;
            })
            ->onUpdated(function (Member $member) use (&$updated): void {
                $updated[] = $member->email;
            });

        // act
        self::$armed = true;
        $sut->upsert($this->rows());

        // assert
        self::assertFalse(self::$armed, 'The hook has not fired.');
        self::assertSame(['a@x' => 'mine-a', 'b@x' => 'mine-b'], $this->stored());
        self::assertSame(['a@x'], $updated);
    }

    /**
     * A cancelled row between the others keeps the positions of the rest right.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCancelledRowBetweenTheOthersKeepsThePositions(): void
    {
        // arrange
        $created = [];
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->onCreating(fn (Member $member) => $member->email === 'm@x' ? false : null)
            ->onCreated(function (Member $member) use (&$created): void {
                $created[] = $member->email;
            });

        // act
        self::$armed = true;
        $sut->upsert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'mine-a'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'm@x', 'name' => 'cancelled'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'mine-b'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'c@x', 'name' => 'mine-c'],
        ]);

        // assert
        self::assertSame(['a@x' => 'mine-a', 'b@x' => 'mine-b', 'c@x' => 'mine-c'], $this->stored());
        sort($created);
        self::assertSame(['b@x', 'c@x'], $created);
    }

    /**
     * When the one-by-one insert after a duplicate meets another error, the rows it has
     * inserted before are undone and the error reaches the caller.
     *
     * @return void
     */
    public function testOtherErrorInTheOneByOneInsertUndoesItsRows(): void
    {
        // act
        self::$armed = true;

        try {
            Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])->upsert([
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'mine-a'],
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'mine-b'],
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'c@x', 'name' => null],
            ]);
            $failed = false;
        } catch (QueryException) {
            $failed = true;
        }

        // assert
        self::assertFalse(self::$armed, 'The hook has not fired.');
        self::assertTrue($failed);
        self::assertSame(['a@x' => 'theirs'], $this->stored());
    }

    /**
     * The rows of the upsert.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return [
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'mine-a'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'mine-b'],
        ];
    }

    /**
     * The stored names by email.
     *
     * @return array<string, string>
     */
    private function stored(): array
    {
        /** @var array<string, string> */
        return DB::table(Member::table())->orderBy('email')->pluck('name', 'email')->all();
    }
}
