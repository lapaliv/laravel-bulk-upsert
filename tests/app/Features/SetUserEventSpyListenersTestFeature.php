<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Mockery\MockInterface;

class SetUserEventSpyListenersTestFeature
{
    /**
     * @param string $model
     * @param array{
     *     creating: MockInterface,
     *     created: MockInterface,
     *     updating: MockInterface,
     *     updated: MockInterface,
     *     saving: MockInterface,
     *     saved: MockInterface,
     * } $listeners
     * @return void
     */
    public function handle(string $model, array $listeners): void
    {
        foreach ($listeners as $event => $listener) {
            call_user_func([$model, $event], $listener);
        }
    }
}
