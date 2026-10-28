<?php

namespace Tests\Unit\Bulk\Update;

use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Exceptions\BulkRowsWereNotSaved;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * A row which the update has not found in the database is not saved, so it gets no events.
 *
 * Regression test. A row given to `update()` which did not exist was skipped by the UPDATE,
 * but still went through `saving`/`savingMany` and `saved`/`savedMany`, and was returned by
 * `updateAndReturn()`. An upsert row which could not be confirmed got `saved` as well.
 *
 * @internal
 */
final class MissingRowEventsTest extends TestCaseWrapper
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
     * The missing row gets neither the saving nor the saved events, and is not returned.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testMissingRowOfUpdateGetsNoEvents(): void
    {
        // arrange
        $events = [];
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onSaving(function (Member $member) use (&$events): void {
                $events[] = 'saving:' . $member->email;
            })
            ->onSavingMany(function ($members) use (&$events): void {
                $events[] = 'savingMany:' . $members->pluck('email')->implode(',');
            })
            ->onSaved(function (Member $member) use (&$events): void {
                $events[] = 'saved:' . $member->email;
            })
            ->onSavedMany(function ($members) use (&$events): void {
                $events[] = 'savedMany:' . $members->pluck('email')->implode(',');
            });

        // act
        $result = $sut->updateAndReturn([
            ['email' => 'missing@x', 'name' => 'new'],
            ['email' => 'a@x', 'name' => 'new'],
        ]);

        // assert
        self::assertSame(
            ['saving:a@x', 'savingMany:a@x', 'saved:a@x', 'savedMany:a@x'],
            $events
        );
        self::assertSame(['a@x'], $result->pluck('email')->all());
        self::assertSame(['new'], DB::table(Member::table())->pluck('name')->all());
    }

    /**
     * A chunk of missing rows only dispatches nothing and returns nothing.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testOnlyMissingRowsDispatchNothing(): void
    {
        // arrange
        $events = 0;
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onSaving(function () use (&$events): void {
                $events++;
            })
            ->onSavedMany(function () use (&$events): void {
                $events++;
            });

        // act
        $result = $sut->updateAndReturn([['email' => 'missing@x', 'name' => 'new']]);

        // assert
        self::assertSame(0, $events);
        self::assertCount(0, $result);
    }

    /**
     * An existing row without changes is still saved (as `Model::save()` of a clean model).
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testExistingRowWithoutChangesKeepsItsEvents(): void
    {
        // arrange
        $saved = [];
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onSaved(function (Member $member) use (&$saved): void {
                $saved[] = $member->email;
            });

        // act
        $sut->update([['email' => 'a@x', 'name' => 'old']]);

        // assert
        self::assertSame(['a@x'], $saved);
    }

    /**
     * A row created by an upsert gets the saving and saved events once, not again in the update part.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowCreatedByUpsertGetsSavingOnce(): void
    {
        // arrange
        $events = [];
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onSaving(function (Member $member) use (&$events): void {
                $events[] = 'saving:' . $member->email;
            })
            ->onSaved(function (Member $member) use (&$events): void {
                $events[] = 'saved:' . $member->email;
            });

        // act
        $sut->upsert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'new'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'new'],
        ]);

        // assert
        sort($events);
        self::assertSame(['saved:a@x', 'saved:b@x', 'saving:a@x', 'saving:b@x'], $events);
    }

    /**
     * An upsert row which could not be confirmed gets no saved event and is not returned.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUnconfirmedUpsertRowGetsNoSavedEvent(): void
    {
        // arrange
        $saved = [];
        $sut = Member::query()->bulk()->uniqueBy('name')
            ->onSaved(function (Member $member) use (&$saved): void {
                $saved[] = $member->email;
            });

        // act
        try {
            $sut->upsert([
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'new'],
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'b'],
            ]);
            $failed = false;
        } catch (BulkRowsWereNotSaved) {
            $failed = true;
        }

        // assert
        self::assertTrue($failed);
        self::assertSame(['b@x'], $saved);
    }
}
