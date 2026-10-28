<?php

namespace Tests\Unit\Bulk\Upsert;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * What `upsert()` writes must not depend on whether the result is asked for.
 *
 * Regression test. `upsertAndReturn()` reads the inserted rows back, so it knew which rows
 * it had created and did not update them. `upsert()` without listeners inserted quietly,
 * took the new rows for existing ones and updated them with the input once more. A database
 * which changes a value on INSERT (a trigger here) ended up with different data: `upsert()`
 * overwrote the change, `upsertAndReturn()` kept it.
 *
 * Now the insert part of an upsert always learns which rows it has created, so both
 * methods write the same and a created row is not updated again.
 *
 * @internal
 */
final class UpsertWriteIndependentOfResultTest extends TestCaseWrapper
{
    /**
     * Clean the table and add the trigger which upper-cases the inserted name.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
        $this->dropTrigger();

        match (DB::connection()->getDriverName()) {
            'sqlite' => DB::unprepared(
                'CREATE TRIGGER members_upper_name AFTER INSERT ON members BEGIN '
                . 'UPDATE members SET name = upper(NEW.name) WHERE id = NEW.id; END'
            ),
            'mysql' => DB::unprepared(
                'CREATE TRIGGER members_upper_name BEFORE INSERT ON members '
                . 'FOR EACH ROW SET NEW.name = UPPER(NEW.name)'
            ),
            'pgsql' => DB::unprepared(
                'CREATE FUNCTION members_upper_name() RETURNS trigger AS $$ '
                . 'BEGIN NEW.name := upper(NEW.name); RETURN NEW; END $$ LANGUAGE plpgsql; '
                . 'CREATE TRIGGER members_upper_name BEFORE INSERT ON members '
                . 'FOR EACH ROW EXECUTE FUNCTION members_upper_name()'
            ),
        };
    }

    /**
     * Drop the trigger.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropTrigger();

        parent::tearDown();
    }

    /**
     * Both methods keep the value the database has stored and send no UPDATE for a new row.
     *
     * @param string $method
     * @param bool $events
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('calls')]
    public function testCreatedRowIsNotUpdatedAgain(string $method, bool $events): void
    {
        // arrange
        $sut = Member::query()->bulk()->uniqueBy('email');
        $events ? $sut->onSaved(fn () => null) : $sut->disableEvents();
        $connection = DB::connection();
        $wasLogging = $connection->logging();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        // act
        try {
            $sut->{$method}([['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'abc']]);
            $queries = $connection->getQueryLog();
        } finally {
            $connection->flushQueryLog();

            if (!$wasLogging) {
                $connection->disableQueryLog();
            }
        }

        $updates = array_filter(
            $queries,
            fn (array $query) => str_starts_with(strtolower(ltrim((string) $query['query'])), 'update')
        );

        // assert
        self::assertSame('ABC', DB::table(Member::table())->value('name'));
        self::assertSame([], array_values($updates));
    }

    /**
     * The upsert methods with and without events.
     *
     * @return array<string, array{string, bool}>
     */
    public static function calls(): array
    {
        return [
            'upsert, no events' => ['upsert', false],
            'upsertAndReturn, no events' => ['upsertAndReturn', false],
            'upsert, events' => ['upsert', true],
            'upsertAndReturn, events' => ['upsertAndReturn', true],
        ];
    }

    /**
     * Drop the trigger and its function, if they exist.
     *
     * @return void
     */
    private function dropTrigger(): void
    {
        match (DB::connection()->getDriverName()) {
            'sqlite', 'mysql' => DB::unprepared('DROP TRIGGER IF EXISTS members_upper_name'),
            'pgsql' => DB::unprepared(
                'DROP TRIGGER IF EXISTS members_upper_name ON members; '
                . 'DROP FUNCTION IF EXISTS members_upper_name()'
            ),
        };
    }
}
