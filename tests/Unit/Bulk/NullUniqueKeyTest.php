<?php

namespace Tests\Unit\Bulk;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * A NULL value of a unique attribute must find its row next to the other values.
 *
 * Regression test. The rows of a chunk were looked up with `whereIn(column, [null, 'x'])`,
 * which Eloquent compiles to `IN (?, ?)`: NULL never matches IN, so the row with NULL was
 * taken for a new one. `update()` skipped it, `upsert()` inserted one more row.
 *
 * The input keys are unambiguous here: the library does not decide between several rows
 * with the same key.
 *
 * @internal
 */
final class NullUniqueKeyTest extends TestCaseWrapper
{
    /**
     * Put a member with a NULL bio and a member with a bio into the table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'old', 'bio' => null],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'old', 'bio' => 'x'],
        ]);
    }

    /**
     * `update()` updates the row with NULL and the row with a value in one chunk.
     *
     * @param string[] $uniqueBy
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('keys')]
    public function testUpdateFindsTheRowWithNull(array $uniqueBy): void
    {
        // act
        Member::query()->bulk()->uniqueBy($uniqueBy)->update([
            $this->row(null, 'new null'),
            $this->row('x', 'new x'),
        ]);

        // assert
        self::assertSame(['new null', 'new x'], $this->names());
    }

    /**
     * `upsert()` updates the row with NULL instead of inserting another one.
     *
     * @param string[] $uniqueBy
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('keys')]
    public function testUpsertDoesNotInsertTheRowWithNullAgain(array $uniqueBy): void
    {
        // act
        // The emails are new, so a row taken for a new one would be inserted, not ignored
        // by the unique index of the email.
        $ids = DB::table(Member::table())->orderBy('email')->pluck('id')->all();
        Member::query()->bulk()->uniqueBy($uniqueBy)->upsert([
            $this->row(null, 'new null') + ['email' => 'a2@x'],
            $this->row('x', 'new x') + ['email' => 'b2@x'],
        ]);

        // assert
        self::assertSame(2, DB::table(Member::table())->count());
        self::assertSame(
            [[$ids[0], 'a2@x', 'new null'], [$ids[1], 'b2@x', 'new x']],
            DB::table(Member::table())->orderBy('id')->get(['id', 'email', 'name'])
                ->map(fn ($row) => [$row->id, $row->email, $row->name])->all()
        );
    }

    /**
     * `forceDelete()` deletes the row with NULL and the row with a value.
     *
     * @param string[] $uniqueBy
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('keys')]
    public function testForceDeleteFindsTheRowWithNull(array $uniqueBy): void
    {
        // act
        Member::query()->bulk()->uniqueBy($uniqueBy)->forceDelete([$this->row(null), $this->row('x')]);

        // assert
        self::assertSame(0, DB::table(Member::table())->count());
    }

    /**
     * A single NULL key keeps working.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSingleNullKey(): void
    {
        // act
        Member::query()->bulk()->uniqueBy('bio')->update([$this->row(null, 'new null')]);

        // assert
        self::assertSame(['new null', 'old'], $this->names());
    }

    /**
     * NULL and an empty string are different keys.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testNullAndEmptyStringAreDifferentKeys(): void
    {
        // arrange
        DB::table(Member::table())->insert(
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'c@x', 'name' => 'old', 'bio' => '']
        );

        // act
        Member::query()->bulk()->uniqueBy('bio')->update([$this->row(null, 'new null'), $this->row('x', 'new x')]);

        // assert
        self::assertSame(['new null', 'new x', 'old'], $this->names());
    }

    /**
     * NULL in a composite key does not reach a row of another combination.
     *
     * `('old', NULL)` and `('n2', 'x')` must not select `('n2', NULL)` or `('old', 'x')`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCompositeKeyWithNullDoesNotMixCombinations(): void
    {
        // arrange
        DB::table(Member::table())->insert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'c@x', 'name' => 'n2', 'bio' => null],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'd@x', 'name' => 'n2', 'bio' => 'x'],
        ]);

        // act
        Member::query()->bulk()->uniqueBy(['name', 'bio'])->update([
            ['name' => 'old', 'bio' => null, 'email' => 'a-new@x'],
            ['name' => 'n2', 'bio' => 'x', 'email' => 'd-new@x'],
        ]);

        // assert
        // a = ('old', NULL), b = ('old', 'x'), c = ('n2', NULL), d = ('n2', 'x')
        self::assertSame(
            ['a-new@x', 'b@x', 'c@x', 'd-new@x'],
            DB::table(Member::table())->orderBy('id')->pluck('email')->all()
        );
    }

    /**
     * A chunk whose only key is NULL finds its row (the IN list is not used at all).
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testOnlyNullKeysInTheChunk(): void
    {
        // act
        Member::query()->bulk()->uniqueBy(['tenant_id', 'bio'])->update([$this->row(null, 'new null')]);
        Member::query()->bulk()->uniqueBy('bio')->forceDelete([$this->row(null)]);

        // assert
        self::assertSame(['b@x'], DB::table(Member::table())->pluck('email')->all());
    }

    /**
     * The unique attributes: a single column and a composite key with NULL in one part.
     *
     * @return array<string, array{string[]}>
     */
    public static function keys(): array
    {
        return [
            'single column' => [['bio']],
            'composite key' => [['tenant_id', 'bio']],
        ];
    }

    /**
     * Build an input row of the current tenant with the given bio.
     *
     * @param string|null $bio
     * @param string|null $name
     *
     * @return array<string, mixed>
     */
    private function row(?string $bio, ?string $name = null): array
    {
        $row = ['tenant_id' => Member::CURRENT_TENANT, 'bio' => $bio];

        return $name === null ? $row : $row + ['name' => $name];
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
