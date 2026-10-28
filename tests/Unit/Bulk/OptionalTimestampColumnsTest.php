<?php

namespace Tests\Unit\Bulk;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * A member without the "updated at" column.
 *
 * @internal
 */
class WithoutUpdatedAtMember extends Member
{
    public const UPDATED_AT = null;

    /**
     * @var string
     */
    protected $table = 'members';
}

/**
 * A member without the "created at" column.
 *
 * @internal
 */
class WithoutCreatedAtMember extends Member
{
    public const CREATED_AT = null;

    /**
     * @var string
     */
    protected $table = 'members';
}

/**
 * A member without both timestamp columns, while `$timestamps` stays `true`.
 *
 * @internal
 */
class WithoutTimestampColumnsMember extends Member
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    /**
     * @var string
     */
    protected $table = 'members';
}

/**
 * A model may disable one timestamp column by setting its constant to `null`.
 *
 * Regression test. Laravel supports `CREATED_AT = null` or `UPDATED_AT = null`, but the
 * package threw `LogicException` ("The "updated at" column of the model is not set.") for
 * every insert of such a model, instead of filling the column which exists.
 *
 * @internal
 */
final class OptionalTimestampColumnsTest extends TestCaseWrapper
{
    /**
     * The time the tests run at.
     *
     * @var string
     */
    private const string NOW = '2026-10-25 13:45:10';

    /**
     * Freeze the time and clean the table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        \Carbon\Carbon::setTestNow(self::NOW);
        DB::table(Member::table())->delete();
    }

    /**
     * Unfreeze the time.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Each model writes only the timestamp columns it has, on create, update and upsert.
     *
     * @param class-string<\Tests\App\Models\Member> $class
     * @param bool $hasCreatedAt
     * @param bool $hasUpdatedAt
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('models')]
    public function testOnlyTheExistingColumnsAreWritten(string $class, bool $hasCreatedAt, bool $hasUpdatedAt): void
    {
        // arrange
        DB::table(Member::table())->insert(
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'old@x', 'name' => 'old']
        );
        $sut = $class::query()->bulk()->uniqueBy('email');

        // act
        $sut->create([$this->row('created@x')]);
        $sut->update([['email' => 'old@x', 'name' => 'updated']]);
        $sut->upsert([$this->row('upserted@x')]);

        // assert
        foreach (['created@x', 'upserted@x'] as $email) {
            $row = DB::table(Member::table())->where('email', $email)->first();
            self::assertNotNull($row);
            self::assertSame($hasCreatedAt ? self::NOW : null, $this->seconds($row->created_at), $email);
            self::assertSame($hasUpdatedAt ? self::NOW : null, $this->seconds($row->updated_at), $email);
        }

        $updated = DB::table(Member::table())->where('email', 'old@x')->first();
        self::assertSame('updated', $updated?->name);
        self::assertNull($updated?->created_at);
        self::assertSame($hasUpdatedAt ? self::NOW : null, $this->seconds($updated?->updated_at));
    }

    /**
     * The models with an optional timestamp column.
     *
     * @return array<string, array{class-string<\Tests\App\Models\Member>, bool, bool}>
     */
    public static function models(): array
    {
        return [
            'both columns' => [Member::class, true, true],
            'without updated_at' => [WithoutUpdatedAtMember::class, true, false],
            'without created_at' => [WithoutCreatedAtMember::class, false, true],
            'without both' => [WithoutTimestampColumnsMember::class, false, false],
        ];
    }

    /**
     * `createAndReturn()` reads the rows back; a missing column must not break it.
     *
     * @param class-string<\Tests\App\Models\Member> $class
     * @param bool $hasCreatedAt
     * @param bool $hasUpdatedAt
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('models')]
    public function testCreateAndReturnWithOptionalColumns(string $class, bool $hasCreatedAt, bool $hasUpdatedAt): void
    {
        // act
        $result = $class::query()->bulk()->uniqueBy('email')->createAndReturn([$this->row('a@x'), $this->row('b@x')]);

        // assert
        self::assertSame(['a@x', 'b@x'], $result->pluck('email')->sort()->values()->all());
        foreach ($result as $member) {
            self::assertTrue($member->exists);
            self::assertNotNull($member->getKey());
        }

        $row = DB::table(Member::table())->where('email', 'a@x')->first();
        self::assertSame($hasCreatedAt ? self::NOW : null, $this->seconds($row?->created_at));
        self::assertSame($hasUpdatedAt ? self::NOW : null, $this->seconds($row?->updated_at));
    }

    /**
     * A timestamp given by the caller is kept for the column which exists.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testGivenTimestampIsKept(): void
    {
        // act
        WithoutUpdatedAtMember::query()->bulk()->uniqueBy('email')->create([
            $this->row('a@x') + ['created_at' => '2020-01-02 03:04:05'],
        ]);

        // assert
        self::assertSame('2020-01-02 03:04:05', $this->seconds(DB::table(Member::table())->value('created_at')));
    }

    /**
     * Build an input row of the current tenant.
     *
     * @param string $email
     *
     * @return array<string, mixed>
     */
    private function row(string $email): array
    {
        return ['tenant_id' => Member::CURRENT_TENANT, 'email' => $email, 'name' => 'new'];
    }

    /**
     * Cut a stored date to the seconds, as the drivers return it differently.
     *
     * @param mixed $value
     *
     * @return string|null
     */
    private function seconds(mixed $value): ?string
    {
        return $value === null ? null : substr((string) $value, 0, 19);
    }
}
