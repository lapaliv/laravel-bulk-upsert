<?php

namespace Tests\Unit\Events;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;

/**
 * Every own listener must get its own key.
 *
 * Regression test. The key used to be the hash of the current time with microseconds,
 * so two listeners added within the same microsecond got the same key and the second
 * one replaced the first one.
 *
 * @internal
 */
final class ListenerKeysTest extends TestCaseWrapper
{
    /**
     * Listeners added one right after another must get different keys.
     *
     * The keys used to be the hash of the current time, so two listeners added within
     * the same microsecond replaced each other.
     *
     * @return void
     */
    public function testListenersAddedTogetherKeepDifferentKeys(): void
    {
        // arrange
        $sut = new BulkEventDispatcher(new User());
        $calls = [];

        // act
        $keys = [];
        for ($i = 0; $i < 100; $i++) {
            $keys[] = $sut->listen(BulkEventEnum::Saving, function () use (&$calls, $i): void {
                $calls[] = $i;
            });
        }
        $sut->forget(BulkEventEnum::Saving, $keys[0]);
        $sut->dispatch(BulkEventEnum::Saving, new User());

        // assert
        self::assertCount(100, array_unique($keys));
        self::assertSame(range(1, 99), $calls);
    }

}
