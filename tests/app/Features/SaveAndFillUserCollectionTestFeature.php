<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Lapaliv\BulkUpsert\Tests\App\Collections\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\User;

class SaveAndFillUserCollectionTestFeature
{
    public function __construct(
        private GenerateUserTestFeature $generateUserFeature
    ) {
        // Nothing
    }

    public function handle(UserCollection $collection, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            /** @var User $user */
            $user = $collection->get($i);
            $user->save();

            $rawUser = $this->generateUserFeature->handle(get_class($user));
            $user->name = $rawUser->name;
            $user->phone = $rawUser->phone;
            $user->date = $rawUser->date;
            $user->microseconds = $rawUser->microseconds;
        }
    }
}
