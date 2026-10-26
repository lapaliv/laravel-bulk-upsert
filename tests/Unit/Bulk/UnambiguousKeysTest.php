<?php

namespace Tests\Unit\Bulk;

use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Support\UniqueKeyGenerator;
use Lapaliv\BulkUpsert\Support\ValueHasher;
use Tests\App\Models\Member;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;

/**
 * The keys which group and match the rows must never merge different values.
 *
 * Regression test. The keys used to be `crc32c` sums of the values, and the parts of a
 * composite key were joined with `:` or `,`. Different values could therefore get the
 * same key: a 32-bit collision (e.g. `u1371838@x.io` and `u2000402@x.io`), a separator
 * inside a value (`('x:y', 'z')` and `('x', 'y:z')`), or `null` against `''`. The rows
 * were then silently merged and one of them was not written.
 *
 * PHP arrays accept strings of any length as keys and resolve their own hash collisions
 * by comparing the whole strings, so the keys are now the encoded values themselves.
 *
 * @internal
 */
final class UnambiguousKeysTest extends TestCaseWrapper
{
    /**
     * Two emails with the same `crc32c` sum.
     *
     * @var string[]
     */
    private const array CRC32C_COLLISION = ['u1371838@x.io', 'u2000402@x.io'];

    /**
     * The emails of every row which the tests of this class write.
     *
     * @var string[]
     */
    private const array EMAILS = [
        'u1371838@x.io',
        'u2000402@x.io',
        'first@x.io',
        'second@x.io',
        'separator-1@x.io',
        'separator-2@x.io',
    ];

    /**
     * Clean the users table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Only the own rows are removed: the other tests leave users with comments behind,
        // and the foreign keys of MySQL and PostgreSQL forbid deleting them.
        DB::table(User::table())->whereIn('email', self::EMAILS)->delete();
    }

    /**
     * Values with the same `crc32c` sum must stay different keys.
     *
     * @return void
     */
    public function testCrc32cCollisionGivesDifferentKeys(): void
    {
        // arrange
        $sut = $this->app->make(ValueHasher::class);

        // act & assert
        self::assertNotSame(
            $sut->handle(self::CRC32C_COLLISION[0]),
            $sut->handle(self::CRC32C_COLLISION[1])
        );
    }

    /**
     * Values of different types or with a different last byte must stay different keys.
     *
     * @return void
     */
    public function testValuesNeverMerge(): void
    {
        // arrange
        $sut = $this->app->make(ValueHasher::class);
        $long = str_repeat('x', 8192);
        $values = [null, '', false, true, 0, 1, '0', '1', 1.5, '1.5', $long . 'a', $long . 'b', "\0\xff"];

        // act
        $keys = array_map(fn (mixed $value) => $sut->handle($value), $values);

        // assert
        self::assertCount(count($values), array_unique($keys));
        self::assertSame($sut->handle('same'), $sut->handle('same'));
    }

    /**
     * The parts of a composite key must keep their boundaries, and null must differ from ''.
     *
     * @return void
     */
    public function testCompositeKeysKeepBoundaries(): void
    {
        // arrange
        $sut = $this->app->make(UniqueKeyGenerator::class);

        // act & assert
        self::assertNotSame(
            $sut->handle(['a' => 'x:y', 'b' => 'z'], ['a', 'b']),
            $sut->handle(['a' => 'x', 'b' => 'y:z'], ['a', 'b'])
        );
        self::assertNotSame(
            $sut->handle(['a' => 'x,y', 'b' => 'z'], ['a', 'b']),
            $sut->handle(['a' => 'x', 'b' => 'y,z'], ['a', 'b'])
        );
        self::assertNotSame($sut->handle(['a' => null], ['a']), $sut->handle(['a' => ''], ['a']));
        // The rows from the input and the rows from the database must keep matching.
        self::assertSame($sut->handle(['a' => 1], ['a']), $sut->handle(['a' => '1'], ['a']));
    }

    /**
     * Both rows with the colliding emails must be updated.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateWritesBothRowsWithCollidingEmails(): void
    {
        // arrange
        DB::table(User::table())->insert([
            $this->user(['name' => 'old 1', 'email' => self::CRC32C_COLLISION[0]]),
            $this->user(['name' => 'old 2', 'email' => self::CRC32C_COLLISION[1]]),
        ]);

        // act
        User::query()->bulk()->uniqueBy('email')->update([
            ['email' => self::CRC32C_COLLISION[0], 'name' => 'new 1'],
            ['email' => self::CRC32C_COLLISION[1], 'name' => 'new 2'],
        ]);

        // assert
        self::assertSame(['new 1', 'new 2'], DB::table(User::table())->whereIn('email', self::EMAILS)->orderBy('id')->pluck('name')->all());
    }

    /**
     * Both rows with the composite keys which differ only in the separator position must be updated.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateWritesBothRowsWithSeparatorsInsideCompositeKey(): void
    {
        // arrange
        DB::table(User::table())->insert([
            $this->user(['name' => 'x:y', 'avatar' => 'z', 'email' => 'separator-1@x.io']),
            $this->user(['name' => 'x', 'avatar' => 'y:z', 'email' => 'separator-2@x.io']),
        ]);

        // act
        User::query()->bulk()->uniqueBy(['name', 'avatar'])->update([
            ['name' => 'x:y', 'avatar' => 'z', 'posts_count' => 1],
            ['name' => 'x', 'avatar' => 'y:z', 'posts_count' => 2],
        ]);

        // assert
        self::assertSame(
            [1, 2],
            DB::table(User::table())->whereIn('email', self::EMAILS)->orderBy('id')->pluck('posts_count')->map(fn ($v) => (int) $v)->all()
        );
    }

    /**
     * The rows which get the same new value must still share one CASE branch value.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateWritesNewValuesWithCollidingSums(): void
    {
        // arrange
        DB::table(User::table())->insert([
            $this->user(['name' => 'old', 'email' => 'first@x.io']),
            $this->user(['name' => 'old', 'email' => 'second@x.io']),
        ]);

        // act
        User::query()->bulk()->uniqueBy('email')->update([
            ['email' => 'first@x.io', 'name' => self::CRC32C_COLLISION[0]],
            ['email' => 'second@x.io', 'name' => self::CRC32C_COLLISION[1]],
        ]);

        // assert
        self::assertSame(self::CRC32C_COLLISION, DB::table(User::table())->whereIn('email', self::EMAILS)->orderBy('id')->pluck('name')->all());
    }

    /**
     * Long texts which differ only at the end must be written as two different values.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateWritesLongTextsWhichDifferAtTheEnd(): void
    {
        // arrange
        $long = str_repeat('long text ', 1000);
        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'a'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@example.com', 'name' => 'b'],
        ]);

        // act
        Member::query()->bulk()->uniqueBy('email')->update([
            ['email' => 'a@example.com', 'bio' => $long . 'a'],
            ['email' => 'b@example.com', 'bio' => $long . 'b'],
        ]);

        // assert
        self::assertSame(
            [$long . 'a', $long . 'b'],
            DB::table(Member::table())->orderBy('email')->pluck('bio')->all()
        );
    }

    /**
     * Build a raw row of the users table.
     *
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    private function user(array $attributes): array
    {
        return $attributes + ['gender' => 'male', 'posts_count' => 0, 'is_admin' => false];
    }
}
