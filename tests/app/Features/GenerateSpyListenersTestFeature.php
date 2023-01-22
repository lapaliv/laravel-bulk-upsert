<?php

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Support\Callback;
use Mockery;
use Mockery\MockInterface;

class GenerateSpyListenersTestFeature
{
    /**
     * @return array{
     *     creating: MockInterface,
     *     created: MockInterface,
     *     updating: MockInterface,
     *     updated: MockInterface,
     *     saving: MockInterface,
     *     saved: MockInterface,
     * }
     */
    public function handle(): array
    {
        return [
            BulkEventEnum::CREATING => Mockery::spy(Callback::class),
            BulkEventEnum::CREATED => Mockery::spy(Callback::class),
            BulkEventEnum::UPDATING => Mockery::spy(Callback::class),
            BulkEventEnum::UPDATED => Mockery::spy(Callback::class),
            BulkEventEnum::SAVING => Mockery::spy(Callback::class),
            BulkEventEnum::SAVED => Mockery::spy(Callback::class),
        ];
    }
}
