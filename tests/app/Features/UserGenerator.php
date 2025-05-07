<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\User;

/**
 * @internal
 */
final class UserGenerator
{
    public function makeCollection(int $count, array $attributes = []): UserCollection
    {
        return User::factory()
            ->count($count)
            ->make($attributes);
    }

    public function makeOne(array $attributes = []): User
    {
        return User::factory()->make($attributes);
    }

    public function createOne(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    public function createOneAndDirty(array $creatingAttributes = [], array $dirtyAttributes = []): User
    {
        $result = User::factory()->create($creatingAttributes);

        $result->created_at = Carbon::now()->subYears(2);
        $result->updated_at = Carbon::now()->subYear();

        if (isset($creatingAttributes['deleted_at'])) {
            $result->deleted_at = $creatingAttributes['deleted_at'];
        }

        $result->save();
        $result->wasRecentlyCreated = false;

        $tmpUser = $this->makeOne($dirtyAttributes);

        $result->name = $tmpUser->name;
        $result->gender = $tmpUser->gender;
        $result->avatar = $tmpUser->avatar;
        $result->posts_count = $tmpUser->posts_count;
        $result->is_admin = $tmpUser->is_admin;
        $result->balance = $tmpUser->balance;
        $result->birthday = $tmpUser->birthday;
        $result->phones = $tmpUser->phones;
        $result->last_visited_at = $tmpUser->last_visited_at;
        $result->deleted_at = $tmpUser->deleted_at;

        return $result;
    }

    public function createCollectionAndDirty(
        int $count,
        array $creatingAttributes = [],
        array $dirtyAttributes = [],
    ): UserCollection
    {
        $result = new UserCollection();

        for ($i = 0; $i < $count; ++$i) {
            $result->push(
                $this->createOneAndDirty($creatingAttributes, $dirtyAttributes)
            );
        }

        return $result;
    }

    public function createCollection(int $count, array $creatingAttributes = []): UserCollection
    {
        $result = new UserCollection();

        for ($i = 0; $i < $count; ++$i) {
            $result->push(
                $this->createOne($creatingAttributes)
            );
        }

        return $result;
    }
}
