<?php

namespace Tests\Unit\Bulk\Update;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * After an update the model must describe exactly what has been written.
 *
 * Regression test. The package called `syncOriginal()`/`syncChanges()` for every row
 * which was not cancelled by `saving`, so the attributes which had not been written
 * (cancelled by `updating`, dropped by `updateOnly()`, a vetoed restore, a primary key,
 * or changed by a listener after the query) were declared saved. A changed primary key
 * of a cancelled row then became the "stored" key and the next write reached another row.
 * A model given with `exists = true` and without the original attributes lost its key.
 *
 * @internal
 */
final class UpdateModelStateTest extends TestCaseWrapper
{
    /**
     * Put two members of the current tenant into the table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'a'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@example.com', 'name' => 'b'],
        ]);
    }

    /**
     * A model built by hand with `exists = true` must keep its key over several writes.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testTrustedModelWithoutOriginalKeepsItsKey(): void
    {
        // arrange
        $id = $this->id('a@example.com');
        $member = new Member(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'first']);
        $member->id = $id;
        $member->exists = true;
        $sut = Member::query()->bulk();

        // act
        $sut->update([$member]);
        $member->name = 'second';
        $sut->update([$member]);
        $sut->forceDelete([$member]);

        // assert
        self::assertSame($id, $member->id);
        self::assertSame(['b@example.com'], DB::table(Member::table())->pluck('email')->all());
    }

    /**
     * A cancelled row must not make its changed primary key the stored one.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCancelledRowKeepsItsStoredKey(): void
    {
        // arrange
        /** @var Member $member */
        $member = Member::query()->where('email', 'a@example.com')->firstOrFail();
        $firstId = $member->id;
        $secondId = $this->id('b@example.com');
        $member->id = $secondId;
        $member->name = 'cancelled';
        $cancel = true;
        $sut = Member::query()->bulk()->onUpdating(function () use (&$cancel) {
            return $cancel ? false : null;
        });

        // act
        $sut->update([$member]);
        $afterCancel = $this->names();
        $cancel = false;
        $member->name = 'written';
        $sut->update([$member]);

        // assert
        self::assertSame(['a', 'b'], $afterCancel);
        self::assertSame(['written', 'b'], $this->names());
        self::assertSame($firstId, $member->getRawOriginal('id'));
    }

    /**
     * The attributes dropped by `updateOnly()` must stay changed and unsaved.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testAttributesOutsideUpdateOnlyStayDirty(): void
    {
        // arrange
        /** @var Member $member */
        $member = Member::query()->where('email', 'a@example.com')->firstOrFail();
        $member->name = 'new name';
        $member->bio = 'new bio';

        // act
        Member::query()->bulk()->updateOnly(['name'])->update([$member]);

        // assert
        self::assertNull(DB::table(Member::table())->where('email', 'a@example.com')->value('bio'));
        self::assertFalse($member->isDirty('name'));
        self::assertTrue($member->isDirty('bio'));
        self::assertArrayHasKey('name', $member->getChanges());
        self::assertArrayNotHasKey('bio', $member->getChanges());
    }

    /**
     * A change made by a listener after the query must stay unsaved.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChangeMadeAfterTheQueryStaysDirty(): void
    {
        // arrange
        /** @var Member $member */
        $member = Member::query()->where('email', 'a@example.com')->firstOrFail();
        $member->name = 'new name';
        $sut = Member::query()->bulk()->onSaved(function (Member $model): void {
            $model->bio = 'set after the query';
        });

        // act
        $sut->update([$member]);

        // assert
        self::assertNull(DB::table(Member::table())->where('email', 'a@example.com')->value('bio'));
        self::assertFalse($member->isDirty('name'));
        self::assertTrue($member->isDirty('bio'));
    }

    /**
     * A cancelled restore must keep the model deleted.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCancelledRestoreKeepsTheModelDeleted(): void
    {
        // arrange
        DB::table(Member::table())->where('email', 'a@example.com')->update(['deleted_at' => '2026-01-01 00:00:00']);
        /** @var Member $member */
        $member = Member::withTrashed()->where('email', 'a@example.com')->firstOrFail();
        $member->name = 'new name';
        $member->deleted_at = null;
        $sut = Member::query()->bulk()->onRestoring(fn () => false);

        // act
        $sut->update([$member]);

        // assert
        self::assertSame('new name', DB::table(Member::table())->where('email', 'a@example.com')->value('name'));
        self::assertNotNull(DB::table(Member::table())->where('email', 'a@example.com')->value('deleted_at'));
        self::assertTrue($member->isDirty('deleted_at'));
        self::assertNotNull($member->getOriginal('deleted_at'));
    }

    /**
     * A listener which changes a written attribute after the query must leave it dirty.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testWrittenAttributeChangedAfterTheQueryStaysDirty(): void
    {
        // arrange
        /** @var Member $member */
        $member = Member::query()->where('email', 'a@example.com')->firstOrFail();
        $member->name = 'written';
        $sut = Member::query()->bulk()->onSaved(function (Member $model): void {
            $model->name = 'changed after the query';
        });

        // act
        $sut->update([$member]);

        // assert
        self::assertSame('written', DB::table(Member::table())->where('email', 'a@example.com')->value('name'));
        self::assertSame('written', $member->getOriginal('name'));
        self::assertTrue($member->isDirty('name'));
        self::assertSame('written', $member->getChanges()['name'] ?? null);
    }

    /**
     * A failed query must not declare anything saved.
     *
     * @return void
     */
    public function testFailedQueryKeepsTheModelDirty(): void
    {
        // arrange
        /** @var Member $member */
        $member = Member::query()->where('email', 'a@example.com')->firstOrFail();
        $member->email = 'b@example.com';

        // act
        try {
            Member::query()->bulk()->update([$member]);
            self::fail('The unique index has to reject the update.');
        } catch (\Illuminate\Database\QueryException) {
            // expected
        }

        // assert
        self::assertTrue($member->isDirty('email'));
        self::assertSame('a@example.com', $member->getRawOriginal('email'));
        self::assertSame([], $member->getChanges());
    }

    /**
     * A cast object taken before the update must stay bound to the model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCastObjectStaysBoundAfterTheUpdate(): void
    {
        // arrange
        DB::table(Member::table())->where('email', 'a@example.com')->update(['settings' => '{}']);
        /** @var Member $member */
        $member = Member::query()->where('email', 'a@example.com')->firstOrFail();
        $settings = $member->settings;
        $settings['first'] = 1;
        $sut = Member::query()->bulk();

        // act
        $sut->update([$member]);
        $settings['second'] = 2;
        $dirtyAfterSecondChange = $member->isDirty('settings');
        $sut->update([$member]);

        // assert
        self::assertTrue($dirtyAfterSecondChange);
        self::assertSame(
            ['first' => 1, 'second' => 2],
            json_decode((string) DB::table(Member::table())->where('email', 'a@example.com')->value('settings'), true)
        );
    }

    /**
     * A row which writes nothing this time must not report the changes of the previous write.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowWhichWritesNothingHasNoChanges(): void
    {
        // arrange
        /** @var Member $member */
        $member = Member::query()->where('email', 'a@example.com')->firstOrFail();
        $member->name = 'first';
        Member::query()->bulk()->update([$member]);
        $member->bio = 'not written';

        // act
        Member::query()->bulk()->updateOnly(['name'])->update([$member]);

        // assert
        self::assertSame([], $member->getChanges());
        self::assertTrue($member->isDirty('bio'));
    }

    /**
     * A key changed by a listener after the query must not redirect the next write.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testKeyChangedAfterTheQueryDoesNotRedirectTheNextWrite(): void
    {
        // arrange
        /** @var Member $member */
        $member = Member::query()->where('email', 'a@example.com')->firstOrFail();
        $secondId = $this->id('b@example.com');
        $member->name = 'first';
        $sut = Member::query()->bulk();
        $sut->onSaved(function (Member $model) use ($secondId): void {
            $model->id = $secondId;
        });

        // act
        $sut->update([$member]);
        $member->name = 'second';
        $sut->update([$member]);

        // assert
        self::assertSame(['second', 'b'], $this->names());
    }

    /**
     * A date object changed in place after the query must stay unsaved.
     *
     * The object is put into the raw attributes, so the listener changes the very value
     * which the statement has written. The written value must be a copy, not the object.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDateChangedInPlaceAfterTheQueryStaysDirty(): void
    {
        // arrange
        /** @var Member $member */
        $member = Member::query()->where('email', 'a@example.com')->firstOrFail();
        $date = \Carbon\Carbon::parse('2026-03-04 05:06:07');
        $member->setRawAttributes(array_replace($member->getAttributes(), ['updated_at' => $date]));
        $sut = Member::query()->bulk()->onSaved(function () use ($date): void {
            $date->addYear();
        });

        // act
        $sut->update([$member]);

        // assert
        $stored = (string) DB::table(Member::table())->where('email', 'a@example.com')->value('updated_at');
        self::assertSame('2026-03-04 05:06:07', substr($stored, 0, 19));
        self::assertSame('2026-03-04 05:06:07', substr((string) $member->getRawOriginal('updated_at'), 0, 19));
        self::assertTrue($member->isDirty('updated_at'));
    }

    /**
     * Get the primary key of the member with the email.
     *
     * @param string $email
     *
     * @return int
     */
    private function id(string $email): int
    {
        return (int) DB::table(Member::table())->where('email', $email)->value('id');
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
