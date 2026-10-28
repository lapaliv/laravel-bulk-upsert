<?php

namespace Tests\Unit\Bulk;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * The unique attributes resolved for a row must be valid and must decide its group.
 *
 * Regression test. The rows were grouped by the index of the `uniqueBy()` entry, so all the
 * rows of one callback shared the attributes of the first of them: a callback which returned
 * `['id']` for one row and `['email']` for another updated the second row by `id`, which it
 * did not have, and skipped it. A callback which returned `null` or `[]` failed later with
 * `Undefined array key 0`, and `uniqueBy([])` failed with the same notice.
 *
 * @internal
 */
final class UniqueByResolutionTest extends TestCaseWrapper
{
    /**
     * Put three members into the table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'old'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'old'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'c@x', 'name' => 'old'],
        ]);
    }

    /**
     * A callback which returns different attributes for different rows updates every row.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCallbackWithDifferentAttributesUpdatesEveryRow(): void
    {
        // arrange
        $idOfA = (int) DB::table(Member::table())->where('email', 'a@x')->value('id');
        $sut = Member::query()->bulk()
            ->uniqueBy(fn (array $row) => array_key_exists('id', $row) ? ['id'] : 'email');

        // act
        $sut->update([
            ['id' => $idOfA, 'name' => 'by id'],
            ['email' => 'b@x', 'name' => 'by email'],
            ['email' => 'c@x', 'name' => 'by email'],
        ]);

        // assert
        self::assertSame(['by id', 'by email', 'by email'], $this->names());
    }

    /**
     * A callback which returns no attributes is rejected with the exception of the package.
     *
     * @param mixed $result
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCallbackResults')]
    public function testCallbackWithoutAttributesIsRejected(mixed $result): void
    {
        // arrange
        $sut = Member::query()->bulk()->uniqueBy(fn () => $result);

        // act
        try {
            $sut->update([['email' => 'a@x', 'name' => 'new']]);
            self::fail('The row has to be rejected.');
        } catch (BulkIdentifierDidNotFind) {
            // expected
        }

        // assert
        self::assertSame(['old', 'old', 'old'], $this->names());
    }

    /**
     * The callback results which do not name any attribute.
     *
     * @return array<string, array{mixed}>
     */
    public static function invalidCallbackResults(): array
    {
        return [
            'null' => [null],
            'empty array' => [[]],
            'empty string' => [''],
            'false' => [false],
            'array with a non-string' => [['email', 1]],
            'array with an empty string' => [['email', '']],
        ];
    }

    /**
     * An empty list of the unique attributes is rejected when it is set.
     *
     * @param string $method
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('settingMethods')]
    public function testEmptyListIsRejected(string $method): void
    {
        // assert
        $this->expectException(InvalidArgumentException::class);

        // act
        Member::query()->bulk()->{$method}([]);
    }

    /**
     * The methods which set the unique attributes.
     *
     * @return array<string, array{string}>
     */
    public static function settingMethods(): array
    {
        return ['uniqueBy' => ['uniqueBy'], 'orUniqueBy' => ['orUniqueBy']];
    }

    /**
     * A callback which does not apply to a row lets the next list of attributes decide.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCallbackWithoutAttributesFallsBackToTheNextList(): void
    {
        // arrange
        $sut = Member::query()->bulk()->uniqueBy(fn () => null);
        // `orUniqueBy()` with lists adds them after the callback.
        $sut->orUniqueBy('email');

        // act
        $sut->update([['email' => 'a@x', 'name' => 'new']]);

        // assert
        self::assertSame(['new', 'old', 'old'], $this->names());
    }

    /**
     * Rows with different callback results which arrive in several calls stay in their groups.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testGroupsOfCallbackSurviveSeveralCalls(): void
    {
        // arrange
        $idOfA = (int) DB::table(Member::table())->where('email', 'a@x')->value('id');
        $sut = Member::query()->bulk()->chunk(2)
            ->uniqueBy(fn (array $row) => array_key_exists('id', $row) ? ['id'] : 'email');

        // act
        $sut->updateOrAccumulate([['email' => 'b@x', 'name' => 'new']]);
        $sut->updateOrAccumulate([['id' => $idOfA, 'name' => 'new']]);
        $sut->updateOrAccumulate([['email' => 'c@x', 'name' => 'new']]);
        $afterAutomatic = $this->names();
        $sut->updateAccumulated();

        // assert
        self::assertSame(['old', 'new', 'new'], $afterAutomatic);
        self::assertSame(['new', 'new', 'new'], $this->names());
    }

    /**
     * A callback which names an attribute the row does not have does not apply to it.
     *
     * The same rule holds for the static lists: the row must have every attribute of the list.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCallbackWithMissingAttributeIsRejected(): void
    {
        // arrange
        $sut = Member::query()->bulk()->uniqueBy(fn () => ['missing']);

        // act
        try {
            $sut->update([['email' => 'a@x', 'name' => 'new']]);
            self::fail('The row has to be rejected.');
        } catch (BulkIdentifierDidNotFind) {
            // expected
        }

        // assert
        self::assertSame(['old', 'old', 'old'], $this->names());
    }

    /**
     * A rejected argument leaves the unique attributes which were set before.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRejectedArgumentKeepsThePreviousAttributes(): void
    {
        // arrange
        $sut = Member::query()->bulk()->uniqueBy('email');

        foreach ([[[]], [''], [['email', '']], [['email'], []]] as $argument) {
            try {
                $sut->uniqueBy($argument);
                self::fail('The argument has to be rejected.');
            } catch (InvalidArgumentException) {
                // expected
            }
        }

        // act
        $sut->update([['email' => 'a@x', 'name' => 'new']]);

        // assert
        self::assertSame(['new', 'old', 'old'], $this->names());
    }

    /**
     * Rows accumulated before and after a change of `uniqueBy()` keep their own attributes.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChangeOfUniqueByBetweenAccumulationsKeepsTheGroups(): void
    {
        // arrange
        $idOfB = (int) DB::table(Member::table())->where('email', 'b@x')->value('id');
        $sut = Member::query()->bulk()->chunk(10);

        // act
        $sut->uniqueBy('email')->updateOrAccumulate([['email' => 'a@x', 'name' => 'by email']]);
        $sut->uniqueBy('id')->updateOrAccumulate([['id' => $idOfB, 'email' => 'b@x', 'name' => 'by id']]);
        $sut->updateAccumulated();

        // assert
        self::assertSame(['by email', 'by id', 'old'], $this->names());
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
