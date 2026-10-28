<?php

namespace Tests\App\Support;

/**
 * A callable object which the tests use as an event listener.
 *
 * The tests wrap it into a Mockery spy and register the spy on the observer. After the
 * bulk operation they check how many times the spy was called and with which arguments.
 *
 * @internal
 */
class TestCallback
{
    /**
     * Handle the call of the listener.
     *
     * The body is empty because only the call itself matters for the tests.
     *
     * @return void
     */
    public function __invoke()
    {
        //
    }
}
