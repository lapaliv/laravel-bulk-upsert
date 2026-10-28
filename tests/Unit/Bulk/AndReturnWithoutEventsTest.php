<?php

namespace Tests\Unit\Bulk;

use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Bulk;
use RuntimeException;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * The failure which the tests inject into the listeners.
 *
 * @internal
 */
final class AndReturnFailure extends RuntimeException
{
    //
}

/**
 * The result of `*AndReturn()` must not depend on the events.
 *
 * Regression test. The result was collected by a temporary `savedMany` listener, so after
 * `disableEvents()` (or without `savedMany` in `setEvents()`) the rows were written and an
 * empty collection was returned. A `once` listener of a call which did not dispatch
 * `savedMany` (every row cancelled) stayed registered and filled the collection of that
 * call during the next one; the listener of `upsertAndReturn()` stayed after an exception.
 *
 * @internal
 */
final class AndReturnWithoutEventsTest extends TestCaseWrapper
{
    /**
     * Clean the members table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
    }

    /**
     * `createAndReturn()` returns the created rows with their keys after `disableEvents()`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCreateAndReturnWithDisabledEvents(): void
    {
        // arrange
        $calls = 0;
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onSaved(function () use (&$calls): void {
                $calls++;
            })
            ->disableEvents();

        // act
        $result = $sut->createAndReturn($this->rows(['a', 'b']));

        // assert
        self::assertSame(0, $calls);
        self::assertSame(['a@x', 'b@x'], $result->pluck('email')->sort()->values()->all());
        self::assertSame(
            $this->idsByEmail(),
            $result->mapWithKeys(fn (Member $member) => [$member->email => (int) $member->id])->sortKeys()->all()
        );
    }

    /**
     * `updateAndReturn()` returns the rows with their changes after `disableEvents()`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateAndReturnWithDisabledEvents(): void
    {
        // arrange
        $this->insert(['a', 'b']);
        $sut = Member::query()->bulk()->uniqueBy('email')->disableEvents();

        // act
        $result = $sut->updateAndReturn([
            ['email' => 'a@x', 'name' => 'new'],
            ['email' => 'b@x', 'name' => 'new'],
        ]);

        // assert
        self::assertSame(['a@x', 'b@x'], $result->pluck('email')->sort()->values()->all());
        foreach ($result as $member) {
            self::assertSame('new', $member->getChanges()['name'] ?? null);
            self::assertFalse($member->isDirty());
        }
    }

    /**
     * `upsertAndReturn()` returns every row once, over several chunks, after `disableEvents()`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpsertAndReturnWithDisabledEvents(): void
    {
        // arrange
        $this->insert(['a']);
        $sut = Member::query()->bulk()->uniqueBy('email')->chunk(1)->disableEvents();

        // act
        $result = $sut->upsertAndReturn($this->rows(['a', 'b', 'c']));

        // assert
        self::assertSame(['a@x', 'b@x', 'c@x'], $result->pluck('email')->sort()->values()->all());
        self::assertSame(['new', 'new', 'new'], $this->names());
    }

    /**
     * The result is the same when `savedMany` is not among the enabled events.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testAndReturnWithoutSavedManyAmongTheEvents(): void
    {
        // arrange
        $this->insert(['a']);
        $savedMany = 0;
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onSavedMany(function () use (&$savedMany): void {
                $savedMany++;
            })
            ->setEvents([BulkEventEnum::Created, BulkEventEnum::Updated]);

        // act
        $created = $sut->createAndReturn($this->rows(['b']));
        $updated = $sut->updateAndReturn([['email' => 'a@x', 'name' => 'new']]);
        $upserted = $sut->upsertAndReturn($this->rows(['a', 'c']));

        // assert
        self::assertSame(0, $savedMany);
        self::assertSame(['b@x'], $created->pluck('email')->all());
        self::assertSame(['a@x'], $updated->pluck('email')->all());
        self::assertSame(['a@x', 'c@x'], $upserted->pluck('email')->sort()->values()->all());
    }

    /**
     * Without events the result is the same as with them, also for ignored conflicts.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testResultWithoutEventsIsTheSameAsWithThem(): void
    {
        // arrange
        $this->insert(['a']);
        $withEvents = Member::query()->bulk()->uniqueBy('email')->onSaved(fn () => null);
        $withoutEvents = Member::query()->bulk()->uniqueBy('email')->disableEvents();

        // act
        $expected = $withEvents->createAndReturn($this->rows(['a', 'b']), ['*'], true);
        DB::table(Member::table())->where('email', 'b@x')->delete();
        $actual = $withoutEvents->createAndReturn($this->rows(['a', 'b']), ['*'], true);

        // assert
        self::assertSame(
            $expected->pluck('email')->sort()->values()->all(),
            $actual->pluck('email')->sort()->values()->all()
        );
    }

    /**
     * A call whose rows are all cancelled does not leave a listener which fills its result later.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCancelledCallDoesNotFillItsResultLater(): void
    {
        // arrange
        $this->insert(['a', 'b']);
        $cancel = true;
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onSaving(function () use (&$cancel) {
                return $cancel ? false : null;
            });

        // act
        $first = $sut->updateAndReturn([['email' => 'a@x', 'name' => 'new']]);
        $cancel = false;
        $second = $sut->updateAndReturn([['email' => 'b@x', 'name' => 'new']]);

        // assert
        self::assertCount(0, $first);
        self::assertSame(['b@x'], $second->pluck('email')->all());
        self::assertSame(0, $this->ownListeners($sut, BulkEventEnum::SavedMany));
    }

    /**
     * A failed call does not leave a temporary listener behind.
     *
     * The failed row stays in the storage, so the test completes it with `upsertAccumulated()`
     * and then checks a later call with another row.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testFailedUpsertDoesNotLeaveItsListener(): void
    {
        // arrange
        $this->insert(['a']);
        $fail = true;
        $sut = Member::query()->bulk()->uniqueBy('email')
            ->onUpdating(function () use (&$fail): void {
                if ($fail) {
                    throw new AndReturnFailure('The update failed.');
                }
            })
            ->onSavedMany(fn () => null);

        // act
        try {
            $sut->upsertAndReturn($this->rows(['a']));
            $failed = false;
        } catch (AndReturnFailure) {
            $failed = true;
        }

        $listenersAfterFailure = $this->ownListeners($sut, BulkEventEnum::SavedMany);
        $fail = false;
        $sut->upsertAccumulated();
        $result = $sut->upsertAndReturn($this->rows(['c']));

        // assert
        self::assertTrue($failed);
        // Only the listener of the test itself is registered.
        self::assertSame(1, $listenersAfterFailure);
        self::assertSame(1, $this->ownListeners($sut, BulkEventEnum::SavedMany));
        self::assertSame(['c@x'], $result->pluck('email')->all());
        self::assertSame(['new', 'new'], $this->names());
    }

    /**
     * Two calls on one bulk instance return independent results.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSequentialCallsReturnIndependentResults(): void
    {
        // arrange
        $sut = Member::query()->bulk()->uniqueBy('email');

        // act
        $first = $sut->createAndReturn($this->rows(['a']));
        $second = $sut->createAndReturn($this->rows(['b']));

        // assert
        self::assertSame(['a@x'], $first->pluck('email')->all());
        self::assertSame(['b@x'], $second->pluck('email')->all());
    }

    /**
     * Count the own listeners of the dispatcher of the bulk for the event.
     *
     * The dispatcher is internal, so it is read through reflection: the test checks that no
     * temporary registration survives a call.
     *
     * @param \Lapaliv\BulkUpsert\Bulk $bulk
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     *
     * @return int
     */
    private function ownListeners(Bulk $bulk, BulkEventEnum $event): int
    {
        $dispatcher = (new \ReflectionProperty(Bulk::class, 'eventDispatcher'))->getValue($bulk);

        if ($dispatcher === null) {
            return 0;
        }

        /** @var array<string, array<string, mixed>> $listeners */
        $listeners = (new \ReflectionProperty($dispatcher, 'listeners'))->getValue($dispatcher);

        return count($listeners[$event->value] ?? []);
    }

    /**
     * Build the input rows of the current tenant named `new`.
     *
     * @param string[] $names
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $names): array
    {
        return array_map(
            fn (string $name) => ['tenant_id' => Member::CURRENT_TENANT, 'email' => $name . '@x', 'name' => 'new'],
            $names
        );
    }

    /**
     * Put members with the given names (`a` becomes `a@x`) into the table.
     *
     * @param string[] $names
     *
     * @return void
     */
    private function insert(array $names): void
    {
        DB::table(Member::table())->insert(array_map(
            fn (string $name) => ['tenant_id' => Member::CURRENT_TENANT, 'email' => $name . '@x', 'name' => 'old'],
            $names
        ));
    }

    /**
     * Get the ids of the members keyed by the email.
     *
     * @return array<string, int>
     */
    private function idsByEmail(): array
    {
        return DB::table(Member::table())->orderBy('email')->pluck('id', 'email')->map(fn ($id) => (int) $id)->all();
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
