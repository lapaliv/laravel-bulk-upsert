<?php

namespace Tests\Unit\Events;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;
use ValueError;

/**
 * `setEvents()` must accept the names of the events, not only the enum cases.
 *
 * Regression test. {@see \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher::restrict()} used
 * to store whatever it was handed:
 *
 *     public function restrict(array $events): static
 *     {
 *         $this->enabledEvents = $events;   // strings stayed strings
 *
 * while {@see \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher::hasListeners()} searches that
 * very list strictly:
 *
 *     if (is_array($this->enabledEvents)
 *         && !in_array($event, $this->enabledEvents, true)   // $event is a BulkEventEnum
 *     ) {
 *         continue;
 *     }
 *
 * A string is never identical to the case it names, so every event fell into the `continue` and
 * `hasListeners()` answered `false` for all 24 of them. `setEvents(['creating'])` therefore muted
 * the whole package instead of enabling one event — and the README documents exactly that call,
 * `@param string[] $events`. The same happened to `enableEvents(['creating'])` on a fresh instance:
 * {@see \Lapaliv\BulkUpsert\Bulk::enableEvents()} hands the list straight to `setEvents()` when
 * nothing has been enabled yet.
 *
 * Silence was the whole failure mode. Nothing threw, no listener ran, and the writes went through,
 * so the only visible symptom was an observer that never fired.
 *
 * `restrict()` now turns the names into their cases, which also makes an unknown name fail loudly
 * instead of muting everything — the behaviour `disableEvent()` and `enableEvent()` already had.
 *
 * @internal
 */
final class SetEventsWithNamesTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * The number of the rows which every test in this class writes.
     *
     * @var int
     */
    private const int ROWS = 2;

    /**
     * An enabled event reaches its listener, whichever form it was named in.
     *
     * @param string[]|\Lapaliv\BulkUpsert\Enums\BulkEventEnum[] $enabledEvents
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('enabledEventsDataProvider')]
    public function testTheEnabledEventIsDispatched(array $enabledEvents): void
    {
        // arrange
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Creating);
        $users = $this->userGenerator->makeCollection(self::ROWS);

        // act
        User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->setEvents($enabledEvents)
            ->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->times(self::ROWS);
    }

    /**
     * The list still restricts: an event which is not in it stays muted.
     *
     * Without this the fix could just as well have been "ignore `enabledEvents` altogether",
     * which would enable everything and lose the feature.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testTheEventsOutsideTheListStayMuted(): void
    {
        // arrange
        User::observe(Observer::class);
        $creating = $this->listenEvent(BulkEventEnum::Creating);
        $saving = $this->listenEvent(BulkEventEnum::Saving);
        $users = $this->userGenerator->makeCollection(self::ROWS);

        // act
        User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->setEvents(['creating'])
            ->create($users);

        // assert
        self::spyShouldHaveReceived($creating)->times(self::ROWS);
        self::spyShouldNotHaveReceived($saving);
    }

    /**
     * `enableEvents()` takes the names as well.
     *
     * On a fresh instance it delegates to `setEvents()` without normalizing the list, so it
     * carried the very same bug.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testEnableEventsAcceptsTheNames(): void
    {
        // arrange
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Creating);
        $users = $this->userGenerator->makeCollection(self::ROWS);

        // act
        User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->enableEvents(['creating'])
            ->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->times(self::ROWS);
    }

    /**
     * A name which belongs to no event is rejected instead of muting the package.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testAnUnknownNameIsRejected(): void
    {
        // assert
        $this->expectException(ValueError::class);

        // act
        User::query()
            ->bulk()
            ->setEvents(['creatingSomethingWhichDoesNotExist']);
    }

    /**
     * The names and the cases are interchangeable, and may be mixed in one list.
     *
     * @return array<string, array{string[]|\Lapaliv\BulkUpsert\Enums\BulkEventEnum[]}>
     */
    public static function enabledEventsDataProvider(): array
    {
        return [
            'names' => [['creating']],
            'cases' => [[BulkEventEnum::Creating]],
            'mixed' => [[BulkEventEnum::Creating, 'created']],
        ];
    }
}
