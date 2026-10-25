<?php

namespace Tests\Unit\Events;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * `enableEvents()` only ever adds — it may never mute an event.
 *
 * Regression test. {@see \Lapaliv\BulkUpsert\Bulk::enableEvents()} used to read the list of the
 * enabled events as
 *
 *     $enabledEvents = $this->getEventDispatcher()->getEnabledEvents() ?? [];
 *
 *     if ($enabledEvents === []) {
 *         return $this->setEvents($events);   // replace the whole list
 *     }
 *
 * {@see \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher::getEnabledEvents()} keeps three
 * states apart: `null` when nothing has been restricted yet and every event is enabled, an empty
 * list when everything has been disabled, and a filled list otherwise. `?? []` flattened the two
 * opposite states into one, and the branch below replaced the whole list for both of them.
 *
 * Replacing is right after `disableEvents()` — everything was off, now these are on. On an
 * untouched instance it is a narrowing: `enableEvents(['creating'])` cut all 24 events down to
 * one, so a method named "enable" muted 23 of them. Calling it was worse than not calling it.
 *
 * This is the same `?? []` conflation which `disableEvents()` and `disableModelEndEvents()`
 * carried, only mirrored: there the list was widened, here it was narrowed.
 *
 * @internal
 */
final class EnableEventsIsAdditiveTest extends TestCaseWrapper
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
     * On an untouched instance every event is enabled, so enabling one more changes nothing.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testEnablingAnEventChangesNothingWhileNothingIsDisabled(): void
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
            ->enableEvents(['creating'])
            ->create($users);

        // assert
        self::spyShouldHaveReceived($creating)->times(self::ROWS);
        self::spyShouldHaveReceived($saving)->times(self::ROWS);
    }

    /**
     * After everything has been disabled, the named event is the only one which comes back.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testEnablingAnEventAfterDisablingAllBringsBackOnlyThatEvent(): void
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
            ->disableEvents()
            ->enableEvents(['creating'])
            ->create($users);

        // assert
        self::spyShouldHaveReceived($creating)->times(self::ROWS);
        self::spyShouldNotHaveReceived($saving);
    }

    /**
     * An event is added to a list which `setEvents()` has written, not swapped for it.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testEnablingAnEventAddsItToAnExplicitList(): void
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
            ->enableEvents(['saving'])
            ->create($users);

        // assert
        self::spyShouldHaveReceived($creating)->times(self::ROWS);
        self::spyShouldHaveReceived($saving)->times(self::ROWS);
    }

    /**
     * An empty argument still means "enable everything", whatever was disabled before.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testEnablingWithoutArgumentsBringsEveryEventBack(): void
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
            ->disableEvents()
            ->enableEvents()
            ->create($users);

        // assert
        self::spyShouldHaveReceived($creating)->times(self::ROWS);
        self::spyShouldHaveReceived($saving)->times(self::ROWS);
    }
}
