<?php

namespace Tests\Unit\Bulk;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * The writes must stay inside the global scopes of the model.
 *
 * Regression test. The package selected the existing rows through the scoped Eloquent
 * query, but then built the UPDATE/DELETE statement from the unique attributes alone.
 * With `UNIQUE(tenant_id, email)`, a tenant scope and `uniqueBy('email')`, the rows of
 * every other tenant with the same email were updated or deleted as well.
 *
 * The statements are now built from the primary keys of the rows which the scoped
 * query has found.
 *
 * @internal
 */
final class GlobalScopeTest extends TestCaseWrapper
{
    /**
     * The tenant which the global scope of the model hides.
     *
     * @var int
     */
    private const int OTHER_TENANT = 2;

    /**
     * Put two tenants with the same emails into the table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'old'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@example.com', 'name' => 'old'],
            ['tenant_id' => self::OTHER_TENANT, 'email' => 'a@example.com', 'name' => 'old'],
            ['tenant_id' => self::OTHER_TENANT, 'email' => 'b@example.com', 'name' => 'old'],
        ]);
    }

    /**
     * `update()` must not touch the rows of another tenant.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateKeepsOtherTenantIntact(): void
    {
        // act
        Member::query()->bulk()->uniqueBy('email')->update([
            ['email' => 'a@example.com', 'name' => 'new a'],
            ['email' => 'b@example.com', 'name' => 'new b'],
        ]);

        // assert
        self::assertSame(['new a', 'new b'], $this->names(Member::CURRENT_TENANT));
        self::assertSame(['old', 'old'], $this->names(self::OTHER_TENANT));
    }

    /**
     * `update()` with selected columns must still write by the found rows only.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateAndReturnWithColumnsKeepsOtherTenantIntact(): void
    {
        // act
        $result = Member::query()->bulk()->uniqueBy('email')->updateAndReturn(
            [['email' => 'a@example.com', 'name' => 'new a']],
            ['name'],
        );

        // assert
        self::assertCount(1, $result);
        self::assertSame(['new a', 'old'], $this->names(Member::CURRENT_TENANT));
        self::assertSame(['old', 'old'], $this->names(self::OTHER_TENANT));
    }

    /**
     * `upsert()` of the existing rows must not touch the rows of another tenant.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpsertKeepsOtherTenantIntact(): void
    {
        // act
        Member::query()->bulk()->uniqueBy('email')->upsert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'new a'],
        ]);

        // assert
        self::assertSame(['new a', 'old'], $this->names(Member::CURRENT_TENANT));
        self::assertSame(['old', 'old'], $this->names(self::OTHER_TENANT));
    }

    /**
     * `delete()` must soft delete the rows of the current tenant only.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeleteKeepsOtherTenantIntact(): void
    {
        // act
        Member::query()->bulk()->uniqueBy('email')->delete([['email' => 'a@example.com']]);

        // assert
        self::assertSame(1, $this->trashed(Member::CURRENT_TENANT));
        self::assertSame(0, $this->trashed(self::OTHER_TENANT));
    }

    /**
     * `forceDelete()` must remove the rows of the current tenant only.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForceDeleteKeepsOtherTenantIntact(): void
    {
        // act
        Member::query()->bulk()->uniqueBy('email')->forceDelete([['email' => 'a@example.com']]);

        // assert
        self::assertSame(1, DB::table(Member::table())->where('tenant_id', Member::CURRENT_TENANT)->count());
        self::assertSame(2, DB::table(Member::table())->where('tenant_id', self::OTHER_TENANT)->count());
    }

    /**
     * A row which exists in another tenant only must not be written at all.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowOfAnotherTenantOnlyIsNotWritten(): void
    {
        // arrange
        DB::table(Member::table())->insert(
            ['tenant_id' => self::OTHER_TENANT, 'email' => 'c@example.com', 'name' => 'old']
        );

        // act
        Member::query()->bulk()->uniqueBy('email')->update([['email' => 'c@example.com', 'name' => 'new']]);
        Member::query()->bulk()->uniqueBy('email')->forceDelete([['email' => 'c@example.com']]);

        // assert
        self::assertSame(
            ['old'],
            DB::table(Member::table())->where('email', 'c@example.com')->pluck('name')->all()
        );
    }

    /**
     * A changed primary key is not written, so the model must keep the stored one.
     *
     * Otherwise `syncOriginal()` remembers the new key and the next write of the same
     * model reaches another row.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChangedPrimaryKeyDoesNotRedirectTheNextWrite(): void
    {
        // arrange
        /** @var Member $first */
        $first = Member::query()->where('email', 'a@example.com')->firstOrFail();
        $secondId = (int) Member::query()->where('email', 'b@example.com')->value('id');
        $first->id = $secondId;
        $first->name = 'new a';
        $sut = Member::query()->bulk();

        // act
        $sut->update([$first]);
        $first->name = 'newer a';
        $sut->update([$first]);

        // assert
        self::assertSame(['newer a', 'old'], $this->names(Member::CURRENT_TENANT));
        self::assertNotSame($secondId, $first->id);
    }

    /**
     * The deleted events must report only the rows which have been deleted.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeletedEventsSkipRowsWhichHaveNotBeenFound(): void
    {
        // arrange
        $deleted = [];
        $deletedMany = [];
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onDeleted(function (Member $member) use (&$deleted): void {
                $deleted[] = $member->email;
            })
            ->onDeletedMany(function ($members) use (&$deletedMany): void {
                foreach ($members as $member) {
                    $deletedMany[] = $member->email;
                }
            });

        // act
        $sut->delete([['email' => 'a@example.com'], ['email' => 'missing@example.com']]);

        // assert
        self::assertSame(['a@example.com'], $deleted);
        self::assertSame(['a@example.com'], $deletedMany);
    }

    /**
     * Get the names of the tenant ordered by the email.
     *
     * @param int $tenant
     *
     * @return string[]
     */
    private function names(int $tenant): array
    {
        return DB::table(Member::table())
            ->where('tenant_id', $tenant)
            ->orderBy('email')
            ->pluck('name')
            ->all();
    }

    /**
     * Count the soft deleted rows of the tenant.
     *
     * @param int $tenant
     *
     * @return int
     */
    private function trashed(int $tenant): int
    {
        return DB::table(Member::table())
            ->where('tenant_id', $tenant)
            ->whereNotNull('deleted_at')
            ->count();
    }
}
