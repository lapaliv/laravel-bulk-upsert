<?php

namespace Tests\Unit\Events;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * Disabling more events must never bring the disabled ones back.
 *
 * Regression test. {@see \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher::getEnabledEvents()}
 * keeps three states apart: `null` — nothing has been restricted yet, so every event is
 * enabled; an empty list — every event has been disabled; a filled list — exactly those.
 * Both {@see \Lapaliv\BulkUpsert\Bulk::disableEvents()} and
 * {@see \Lapaliv\BulkUpsert\Bulk::disableModelEndEvents()} used to flatten the first two into
 * one:
 *
 *     $enabledEvents = $this->normalizeEvents($this->getEventDispatcher()->getEnabledEvents() ?? []);
 *
 *     if ($enabledEvents === []) {
 *         $enabledEvents = BulkEventEnum::cases();   // "nothing is enabled" read as "everything is"
 *     }
 *
 * `?? []` turns the `null` into an empty list, and the branch below then cannot tell that
 * empty list from the one `disableEvents()` itself had written. So a second, narrower call
 * started from all 24 cases and enabled everything it was not asked to remove:
 *
 *     $bulk->disableEvents();              // every event is off
 *     $bulk->disableEvents(['updating']);  // ... and now every event except `updating` is on
 *
 * Both methods narrow the list, so neither could ever widen it. The list is now resolved
 * once, by {@see \Lapaliv\BulkUpsert\Bulk::resolveEnabledEvents()}, which reads the `null`
 * and the empty list as the different things they are.
 *
 * @internal
 */
final class DisableEventsAfterDisablingAllTest extends TestCaseWrapper
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
     * A narrower `disableEvents()` leaves the events which are already off alone.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDisablingOneMoreEventKeepsTheRestDisabled(): void
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
            ->disableEvents(['updating'])
            ->create($users);

        // assert
        self::spyShouldNotHaveReceived($creating);
        self::spyShouldNotHaveReceived($saving);
    }

    /**
     * `disableModelEndEvents()` carried the very same branch, so it could widen the list too.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDisablingTheModelEndEventsKeepsTheRestDisabled(): void
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
            ->disableModelEndEvents()
            ->create($users);

        // assert
        self::spyShouldNotHaveReceived($creating);
        self::spyShouldNotHaveReceived($saving);
    }

    /**
     * On an untouched instance every event is enabled, so the named one is the only loss.
     *
     * The `null` and the empty list must not be flattened the other way round either: reading
     * "everything is enabled" as "nothing is" would mute the whole package here.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDisablingOneEventLeavesTheOthersEnabled(): void
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
            ->disableEvents(['creating'])
            ->create($users);

        // assert
        self::spyShouldNotHaveReceived($creating);
        self::spyShouldHaveReceived($saving)->times(self::ROWS);
    }

    /**
     * Narrowing a list which `setEvents()` has written keeps the rest of it.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDisablingAnEventOutOfAnExplicitListKeepsTheOthers(): void
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
            ->setEvents(['creating', 'saving'])
            ->disableEvents(['saving'])
            ->create($users);

        // assert
        self::spyShouldHaveReceived($creating)->times(self::ROWS);
        self::spyShouldNotHaveReceived($saving);
    }
}
