<?php

namespace Tests\Unit\Bulk;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * The matching by the database equality on SQLite, with a `COLLATE NOCASE` column.
 *
 * @internal
 */
final class SqLiteNocaseKeyTest extends TestCaseWrapper
{
    /**
     * Create a members table whose email compares case-insensitively.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite') {
            self::markTestSkipped('The column collation is set by a SQLite statement.');
        }

        DB::unprepared('DROP TABLE IF EXISTS members_ci');
        DB::unprepared(
            'CREATE TABLE members_ci (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, '
            . 'email VARCHAR(255) COLLATE NOCASE NOT NULL, name VARCHAR(255) NOT NULL, bio TEXT NULL, '
            . 'settings TEXT NULL, created_at DATETIME NULL, updated_at DATETIME NULL, deleted_at DATETIME NULL, '
            . 'UNIQUE (tenant_id, email))'
        );
        DB::table('members_ci')->insert(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'user@x', 'name' => 'old']);
    }

    /**
     * Drop the table.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (DB::getDefaultConnection() === 'sqlite') {
            DB::unprepared('DROP TABLE IF EXISTS members_ci');
        }

        parent::tearDown();
    }

    /**
     * Update, upsert and delete find the stored row.
     *
     * @param string $method
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('methods')]
    public function testStoredRowIsFound(string $method): void
    {
        // act
        (new Member())->setTable('members_ci')->newQuery()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->{$method}([['tenant_id' => Member::CURRENT_TENANT, 'email' => 'USER@x', 'name' => 'new']]);

        // assert
        $expected = $method === 'forceDelete' ? [] : ['user@x' => 'new'];
        self::assertSame($expected, DB::table('members_ci')->pluck('name', 'email')->all());
    }

    /**
     * The methods.
     *
     * @return array<string, array{string}>
     */
    public static function methods(): array
    {
        return [
            'update' => ['update'],
            'upsert' => ['upsert'],
            'upsertAndReturn' => ['upsertAndReturn'],
            'forceDelete' => ['forceDelete'],
        ];
    }
}
