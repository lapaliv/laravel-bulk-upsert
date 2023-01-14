<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

class PrepareUpsertedCollectionFeature
{
    public function __construct(
        private BulkFireModelEventsFeature $fireModelEventsFeature,
    )
    {
        //
    }

    public function handle(Collection $collection, array $events): void
    {
        $collection->map(
            function (BulkModel $model) use ($events): void {
                $this->fireModelEventsFeature->handle($model, $events, [
                    BulkEventEnum::CREATED,
                    BulkEventEnum::SAVED,
                ]);

                $model->syncChanges();
            }
        );
    }
}
