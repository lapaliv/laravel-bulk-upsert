<?php

namespace Tests\Unit\Bulk\Upsert;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * A new row whose creation a listener cancels is not written by the update part either.
 *
 * Regression test. The cancelled row was removed from the insert only; the upsert then took
 * it for a row which had lost the race and sent it to the update. When the row existed by
 * then (e.g. the listener had stored it in its own way), it was overwritten with the input
 * in spite of `false`.
 *
 * @internal
 */
final class UpsertCreatingCancellationTest extends TestCaseWrapper
{
    /**
     * Clear the table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
    }

    /**
     * `creating` stores the row itself and cancels: the stored row stays as it is.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowStoredByTheCancellingListenerIsNotOverwritten(): void
    {
        // arrange
        $updated = [];
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->onCreating(function (Member $member): bool {
                DB::table(Member::table())->insert(
                    ['tenant_id' => $member->tenant_id, 'email' => $member->email, 'name' => 'alternative']
                );

                return false;
            })
            ->onUpdated(function (Member $member) use (&$updated): void {
                $updated[] = $member->email;
            });

        // act
        $sut->upsert([['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'input']]);

        // assert
        self::assertSame(['a@x' => 'alternative'], $this->stored());
        self::assertSame([], $updated);
    }

    /**
     * The same with `creatingMany`, and the returned models do not contain the cancelled row.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCreatingManyCancellation(): void
    {
        // arrange
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->onCreatingMany(function ($members): bool {
                foreach ($members as $member) {
                    DB::table(Member::table())->insert(
                        ['tenant_id' => $member->tenant_id, 'email' => $member->email, 'name' => 'alternative']
                    );
                }

                return false;
            });

        // act
        $result = $sut->upsertAndReturn([['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'input']]);

        // assert
        self::assertSame(['a@x' => 'alternative'], $this->stored());
        self::assertCount(0, $result);
    }

    /**
     * In a mixed chunk the other rows are created and updated as usual.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testMixedChunk(): void
    {
        // arrange
        DB::table(Member::table())->insert(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'old@x', 'name' => 'old']);
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->onCreating(function (Member $member): ?bool {
                if ($member->email !== 'c@x') {
                    return null;
                }

                DB::table(Member::table())->insert(
                    ['tenant_id' => $member->tenant_id, 'email' => $member->email, 'name' => 'alternative']
                );

                return false;
            });

        // act
        $sut->upsert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'c@x', 'name' => 'input'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'new@x', 'name' => 'input'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'old@x', 'name' => 'input'],
        ]);

        // assert
        self::assertSame(
            ['c@x' => 'alternative', 'new@x' => 'input', 'old@x' => 'input'],
            $this->stored()
        );
    }

    /**
     * `saving` / `savingMany` which store the row themselves and cancel: the row stays, gets no
     * `saved`, is not returned and is not retried by the next flush.
     *
     * @param string $event
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('savingEvents')]
    public function testSavingCancellation(string $event): void
    {
        // arrange
        $saved = [];
        $store = static function (Member $member): void {
            if (!$member->exists && DB::table(Member::table())->where('email', $member->email)->doesntExist()) {
                DB::table(Member::table())->insert(
                    ['tenant_id' => $member->tenant_id, 'email' => $member->email, 'name' => 'alternative']
                );
            }
        };
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])->chunk(10)
            ->onSaved(function (Member $member) use (&$saved): void {
                $saved[] = $member->email;
            });
        $sut = $event === 'saving'
            ? $sut->onSaving(function (Member $member) use ($store): ?bool {
                $store($member);

                return $member->exists ? null : false;
            })
            : $sut->onSavingMany(function ($members) use ($store): ?bool {
                $new = false;

                foreach ($members as $member) {
                    $new = $new || !$member->exists;
                    $store($member);
                }

                return $new ? false : null;
            });

        // act
        $sut->upsertOrAccumulate([['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'input']]);
        $result = $sut->upsertAndReturn([]);
        $sut->upsertAccumulated();
        $sut->upsertAccumulated();

        // assert
        self::assertSame(['a@x' => 'alternative'], $this->stored());
        self::assertSame([], $saved);
        self::assertCount(0, $result);
    }

    /**
     * The saving events.
     *
     * @return array<string, array{string}>
     */
    public static function savingEvents(): array
    {
        return ['saving' => ['saving'], 'savingMany' => ['savingMany']];
    }

    /**
     * The stored names by email.
     *
     * @return array<string, string>
     */
    private function stored(): array
    {
        /** @var array<string, string> */
        return DB::table(Member::table())->orderBy('email')->pluck('name', 'email')->all();
    }
}
