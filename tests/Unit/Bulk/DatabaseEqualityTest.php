<?php

namespace Tests\Unit\Bulk;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * A unique key which the database finds equal to the stored one, though PHP does not.
 *
 * Regression test. On a case-insensitive collation (the MySQL default) the lookup found the
 * stored `user@x` for the input `USER@x`, but the rows were matched in PHP byte by byte:
 * `update()` and `delete()` skipped the row without a word and `upsert()` failed with
 * "Call to a member function getModel() on null".
 *
 * The tests run where the column really compares case-insensitively; elsewhere the two
 * values are different keys and the test is skipped.
 *
 * @internal
 */
final class DatabaseEqualityTest extends TestCaseWrapper
{
    /**
     * Store `user@x` and skip unless the database finds it by `USER@x`.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'user@x', 'name' => 'old']);

        if (!DB::table(Member::table())->where('email', 'USER@x')->exists()) {
            self::markTestSkipped('The email column compares case-sensitively here.');
        }
    }

    /**
     * `update()` updates the stored row.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdate(): void
    {
        // act
        Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->update([['tenant_id' => Member::CURRENT_TENANT, 'email' => 'USER@x', 'name' => 'new']]);

        // assert
        self::assertSame(['user@x' => 'new'], $this->stored());
    }

    /**
     * `upsert()` updates the stored row instead of failing or creating another one.
     *
     * @param string $method
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('upsertMethods')]
    public function testUpsert(string $method): void
    {
        // act
        Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])->{$method}([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'USER@x', 'name' => 'new'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'other@x', 'name' => 'other'],
        ]);

        // assert
        self::assertSame(['other@x' => 'other', 'user@x' => 'new'], $this->stored());
    }

    /**
     * The upsert methods.
     *
     * @return array<string, array{string}>
     */
    public static function upsertMethods(): array
    {
        return ['upsert' => ['upsert'], 'upsertAndReturn' => ['upsertAndReturn']];
    }

    /**
     * `delete()` and `forceDelete()` remove the stored row.
     *
     * @param string $method
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('deleteMethods')]
    public function testDelete(string $method): void
    {
        // act
        Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->{$method}([['tenant_id' => Member::CURRENT_TENANT, 'email' => 'USER@x']]);

        // assert
        self::assertSame([], $this->stored());
    }

    /**
     * The delete methods.
     *
     * @return array<string, array{string}>
     */
    public static function deleteMethods(): array
    {
        return ['delete' => ['delete'], 'forceDelete' => ['forceDelete']];
    }

    /**
     * Exact, differently written and missing keys in one chunk, in any order: each row gets
     * its own stored row, and the returned models carry the stored key.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testMixedChunk(): void
    {
        // arrange
        DB::table(Member::table())->insert(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'exact@x', 'name' => 'old']);
        $ids = DB::table(Member::table())->pluck('id', 'email')->all();

        // act
        $result = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])->upsertAndReturn([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'new@x', 'name' => 'n'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'USER@x', 'name' => 'u'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'exact@x', 'name' => 'e'],
        ]);

        // assert
        self::assertSame(['exact@x' => 'e', 'new@x' => 'n', 'user@x' => 'u'], $this->stored());
        self::assertSame($ids['user@x'], DB::table(Member::table())->where('name', 'u')->value('id'));
        self::assertSame(
            ['exact@x', 'new@x', 'user@x'],
            $result->pluck('email')->sort()->values()->all()
        );
    }

    /**
     * The row of another tenant with the same email is never taken.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testAnotherTenantIsNotTaken(): void
    {
        // arrange
        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert(['tenant_id' => 2, 'email' => 'user@x', 'name' => 'tenant 2']);

        // act
        Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->update([['tenant_id' => Member::CURRENT_TENANT, 'email' => 'USER@x', 'name' => 'new']]);

        // assert
        self::assertSame(['tenant 2'], DB::table(Member::table())->pluck('name')->all());
    }

    /**
     * An input with the email of A (written differently) and the id of B writes into A only.
     *
     * @param string $method
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('writingMethods')]
    public function testForeignIdInTheInputDoesNotRedirectTheWrite(string $method): void
    {
        // arrange
        DB::table(Member::table())->insert(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'b']);
        $ids = DB::table(Member::table())->pluck('id', 'email')->all();

        // act
        $result = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])->{$method}([
            ['id' => $ids['b@x'], 'tenant_id' => Member::CURRENT_TENANT, 'email' => 'USER@x', 'name' => 'new'],
        ]);

        // assert
        self::assertSame(['b@x' => 'b', 'user@x' => 'new'], $this->stored());
        self::assertSame($ids, DB::table(Member::table())->pluck('id', 'email')->all());

        if ($method === 'upsertAndReturn') {
            self::assertSame($ids['user@x'], $result->first()?->getKey());
        }
    }

    /**
     * The writing methods.
     *
     * @return array<string, array{string}>
     */
    public static function writingMethods(): array
    {
        return ['update' => ['update'], 'upsert' => ['upsert'], 'upsertAndReturn' => ['upsertAndReturn']];
    }

    /**
     * An unmatched candidate of the chunk does not let the row of another tenant in.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testAnotherTenantIsNotTakenNextToACandidate(): void
    {
        // arrange
        DB::table(Member::table())->insert(['tenant_id' => 2, 'email' => 'other@x', 'name' => 'tenant 2']);

        // act
        Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])->update([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'USER@x', 'name' => 'new'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'OTHER@x', 'name' => 'stolen'],
        ]);

        // assert
        self::assertSame(
            ['other@x' => 'tenant 2', 'user@x' => 'new'],
            DB::table(Member::table())->orderBy('email')->pluck('name', 'email')->all()
        );
    }

    /**
     * The stored names of the rows which are not deleted, by email.
     *
     * @return array<string, string>
     */
    private function stored(): array
    {
        /** @var array<string, string> */
        return DB::table(Member::table())->whereNull('deleted_at')->orderBy('email')->pluck('name', 'email')->all();
    }
}
