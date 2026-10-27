<?php

namespace Tests\Unit\Bulk;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * The queries which the writes depend on must run on the write connection.
 *
 * Regression test. PostgreSQL ran `INSERT ... RETURNING` with `select()`, i.e. on the
 * read connection, and without marking the connection as modified. The `max(id)` read
 * before an insert and the lookup of the existing rows also used the read connection,
 * so with a lagging replica the package could miss the rows which it was about to
 * update or had just inserted.
 *
 * The test connection writes to the real database and reads from an unreachable one:
 * every query which goes to the read connection fails.
 *
 * @internal
 */
final class ReadWriteConnectionTest extends TestCaseWrapper
{
    /**
     * The name of the read/write connection.
     *
     * @var string
     */
    private const string CONNECTION = 'bulk_read_write';

    /**
     * Register the connection and clean the table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var array<string, mixed> $config */
        $config = DB::connection()->getConfig();
        $driver = (string) ($config['driver'] ?? '');
        $read = $driver === 'sqlite'
            ? ['database' => '/nonexistent/bulk-upsert/read.sqlite']
            : ['host' => '127.0.0.1', 'port' => 1];

        DB::connectUsing(
            self::CONNECTION,
            array_replace($config, ['read' => $read, 'write' => [], 'sticky' => false]),
            true
        );

        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert(
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'old']
        );
    }

    /**
     * Drop the connection.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        parent::tearDown();
    }

    /**
     * `create()` which reads the inserted rows back must not use the read connection.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCreateWithResultUsesTheWriteConnection(): void
    {
        // arrange
        $created = [];
        $sut = $this->model()->newQuery()->bulk()->uniqueBy('email')
            ->onCreated(function (Member $member) use (&$created): void {
                $created[] = $member->email;
            });

        // act
        $sut->create([$this->row('b@example.com', 'b')]);

        // assert
        self::assertSame(['b@example.com'], $created);
        self::assertSame('b', DB::table(Member::table())->where('email', 'b@example.com')->value('name'));
        self::assertTrue(DB::connection(self::CONNECTION)->hasModifiedRecords());
    }

    /**
     * `update()` must look the existing rows up on the write connection.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateUsesTheWriteConnection(): void
    {
        // act
        $this->model()->newQuery()->bulk()->uniqueBy('email')->update([
            ['email' => 'a@example.com', 'name' => 'new'],
        ]);

        // assert
        self::assertSame('new', DB::table(Member::table())->where('email', 'a@example.com')->value('name'));
    }

    /**
     * `upsert()` must look the rows up and read them back on the write connection.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpsertUsesTheWriteConnection(): void
    {
        // act
        $result = $this->model()->newQuery()->bulk()->uniqueBy('email')->upsertAndReturn([
            $this->row('a@example.com', 'updated'),
            $this->row('b@example.com', 'created'),
        ]);

        // assert
        self::assertCount(2, $result);
        self::assertSame(
            ['updated', 'created'],
            DB::table(Member::table())->orderBy('email')->pluck('name')->all()
        );
    }

    /**
     * `delete()` must look the rows up on the write connection.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForceDeleteUsesTheWriteConnection(): void
    {
        // act
        $this->model()->newQuery()->bulk()->uniqueBy('email')->forceDelete([['email' => 'a@example.com']]);

        // assert
        self::assertSame(0, DB::table(Member::table())->count());
    }

    /**
     * Get a member model on the read/write connection.
     *
     * @return \Tests\App\Models\Member
     */
    private function model(): Member
    {
        return (new Member())->setConnection(self::CONNECTION);
    }

    /**
     * Build an input row of the current tenant.
     *
     * @param string $email
     * @param string $name
     *
     * @return array<string, mixed>
     */
    private function row(string $email, string $name): array
    {
        return ['tenant_id' => Member::CURRENT_TENANT, 'email' => $email, 'name' => $name];
    }
}
