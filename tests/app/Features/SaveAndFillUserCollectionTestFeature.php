<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Lapaliv\BulkUpsert\Tests\App\Collections\UserCollection;

class SaveAndFillUserCollectionTestFeature
{
    public function __construct(
        private GenerateUserTestFeature $generateUserFeature
    )
    {
        // Nothing
    }

    public function handle(UserCollection $collection, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = $collection->get($i);
            $user->save();

            $user->fill(
                $this->generateUserFeature->handle(get_class($user))
                    ->only('name', 'phone', 'date', 'microseconds')
            );
        }
    }
}
