<?php

namespace Tests\Unit\Bulk\Update;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * The changes made by a `savingMany` listener are written, as the ones of `saving`.
 *
 * Regression test. A row which was clean after `saving` was marked as "nothing to update"
 * before `savingMany` ran, so the changes of `savingMany` were neither written nor reported.
 *
 * @internal
 */
final class SavingManyChangesTest extends TestCaseWrapper
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
        DB::table(Member::table())->insert(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'old']);
    }

    /**
     * A clean model changed by `savingMany` is updated and gets `updated`/`saved`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChangeOfACleanModelIsWritten(): void
    {
        // arrange
        $events = [];
        $model = Member::query()->firstOrFail();
        $sut = Member::query()->bulk()
            ->onSavingMany(function ($models): void {
                foreach ($models as $member) {
                    $member->name = 'set by savingMany';
                }
            })
            ->onUpdated(function (Member $member) use (&$events): void {
                $events[] = 'updated:' . $member->name;
            })
            ->onSaved(function (Member $member) use (&$events): void {
                $events[] = 'saved:' . $member->name;
            });

        // act
        $result = $sut->updateAndReturn([$model]);

        // assert
        self::assertSame(['set by savingMany'], DB::table(Member::table())->pluck('name')->all());
        self::assertSame(['updated:set by savingMany', 'saved:set by savingMany'], $events);
        self::assertSame('set by savingMany', $result->first()?->name);
        self::assertFalse($model->isDirty());
    }

    /**
     * A change reverted by `savingMany` is not written and the row gets no `updated`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChangeRevertedBySavingManyIsNotWritten(): void
    {
        // arrange
        $updated = 0;
        $model = Member::query()->firstOrFail();
        $model->name = 'new';
        $sut = Member::query()->bulk()
            ->onSavingMany(function ($models): void {
                foreach ($models as $member) {
                    $member->name = 'old';
                }
            })
            ->onUpdated(function () use (&$updated): void {
                $updated++;
            });

        // act
        $sut->update([$model]);

        // assert
        self::assertSame(['old'], DB::table(Member::table())->pluck('name')->all());
        self::assertSame(0, $updated);
    }

    /**
     * A row cancelled by `saving` stays cancelled, whatever `savingMany` changes.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowCancelledBySavingStaysCancelled(): void
    {
        // arrange
        $model = Member::query()->firstOrFail();
        $model->name = 'new';
        $sut = Member::query()->bulk()
            ->onSaving(fn () => false)
            ->onSavingMany(function ($models): void {
                foreach ($models as $member) {
                    $member->name = 'set by savingMany';
                }
            });

        // act
        $sut->update([$model]);

        // assert
        self::assertSame(['old'], DB::table(Member::table())->pluck('name')->all());
    }

    /**
     * A row cancelled by `updating` stays cancelled after `savingMany` has changed it.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowCancelledByUpdatingStaysCancelled(): void
    {
        // arrange
        $model = Member::query()->firstOrFail();
        $sut = Member::query()->bulk()
            ->onSavingMany(function ($models): void {
                foreach ($models as $member) {
                    $member->name = 'set by savingMany';
                }
            })
            ->onUpdating(fn () => false);

        // act
        $sut->update([$model]);

        // assert
        self::assertSame(['old'], DB::table(Member::table())->pluck('name')->all());
    }

    /**
     * The upsert of an existing row writes the changes of `savingMany` as well.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpsertWritesTheChangeOfSavingMany(): void
    {
        // arrange
        $sut = Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->onSavingMany(function ($models): void {
                foreach ($models as $member) {
                    if ($member->exists) {
                        $member->name = 'set by savingMany';
                    }
                }
            });

        // act
        $sut->upsert([['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'old']]);

        // assert
        self::assertSame(['set by savingMany'], DB::table(Member::table())->pluck('name')->all());
    }

    /**
     * `savingMany` returning false cancels every row, whatever it has changed.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSavingManyReturningFalseWritesNothing(): void
    {
        // arrange
        $model = Member::query()->firstOrFail();
        $sut = Member::query()->bulk()->onSavingMany(function ($models): bool {
            foreach ($models as $member) {
                $member->name = 'set by savingMany';
            }

            return false;
        });

        // act
        $sut->update([$model]);

        // assert
        self::assertSame(['old'], DB::table(Member::table())->pluck('name')->all());
    }

    /**
     * In a mixed chunk a clean row gets no `updating`, a changed one does, a cancelled one not.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testMixedChunk(): void
    {
        // arrange
        DB::table(Member::table())->insert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'old'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'c@x', 'name' => 'old'],
        ]);
        $models = Member::query()->orderBy('email')->get();
        $models[1]->name = 'changed';
        $models[2]->name = 'cancelled';
        $updating = [];
        $sut = Member::query()->bulk()
            ->onSaving(fn (Member $member) => $member->email === 'c@x' ? false : null)
            ->onSavingMany(fn () => null)
            ->onUpdating(function (Member $member) use (&$updating): void {
                $updating[] = $member->email;
            });

        // act
        $sut->update($models);

        // assert
        self::assertSame(['b@x'], $updating);
        self::assertSame(
            ['a@x' => 'old', 'b@x' => 'changed', 'c@x' => 'old'],
            DB::table(Member::table())->orderBy('email')->pluck('name', 'email')->all()
        );
    }
}
