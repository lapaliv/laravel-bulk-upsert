<?php

namespace Tests\Unit\Bulk\Update;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * A member whose name has a mutator which is not idempotent.
 *
 * @internal
 */
class SuffixedNameMember extends Member
{
    /**
     * @var string
     */
    protected $table = 'members';

    /**
     * Add a suffix to the name.
     *
     * @param string $value
     *
     * @return void
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value . '!';
    }
}

/**
 * A mutator must run once for every value given as an array.
 *
 * Regression test. The arrays were turned into models (the mutator ran), and after the
 * existing rows had been selected the values were copied into the selected models with
 * `setAttribute()`, so the mutator ran a second time: `new` was stored as `new!!`.
 *
 * @internal
 */
final class MutatorAppliedOnceTest extends TestCaseWrapper
{
    /**
     * Put one member into the table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert(
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'old']
        );
    }

    /**
     * `update()` must run the mutator once.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateRunsTheMutatorOnce(): void
    {
        // act
        SuffixedNameMember::query()->bulk()->uniqueBy('email')->update([
            ['email' => 'a@example.com', 'name' => 'new'],
        ]);

        // assert
        self::assertSame('new!', DB::table(Member::table())->value('name'));
    }

    /**
     * `upsert()` must run the mutator once for the existing and for the new rows.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpsertRunsTheMutatorOnce(): void
    {
        // act
        SuffixedNameMember::query()->bulk()->uniqueBy('email')->upsert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'updated'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@example.com', 'name' => 'created'],
        ]);

        // assert
        self::assertSame(
            ['updated!', 'created!'],
            DB::table(Member::table())->orderBy('email')->pluck('name')->all()
        );
    }

    /**
     * The selected model must still know which values have changed.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testReturnedModelReportsTheChange(): void
    {
        // act
        $result = SuffixedNameMember::query()->bulk()->uniqueBy('email')->updateAndReturn([
            ['email' => 'a@example.com', 'name' => 'new'],
        ]);

        // assert
        self::assertCount(1, $result);
        self::assertSame('new!', $result->first()?->name);
        self::assertSame('new!', $result->first()?->getChanges()['name'] ?? null);
        self::assertFalse((bool) $result->first()?->isDirty());
    }
}
