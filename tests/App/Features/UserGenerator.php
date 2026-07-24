<?php

namespace Tests\App\Features;

use Carbon\Carbon;
use Tests\App\Collection\UserCollection;
use Tests\App\Models\User;

/**
 * Builds the users which the tests need.
 *
 * The class wraps the user factory and gives the tests a few ready made cases: a fresh
 * user, a saved user and a saved user whose attributes were changed after the writing.
 *
 * @internal
 */
final class UserGenerator
{
    /**
     * Create a collection of users which are not saved yet.
     *
     * @param int $count
     * @param array $attributes
     *
     * @return \Tests\App\Collection\UserCollection
     */
    public function makeCollection(int $count, array $attributes = []): UserCollection
    {
        return User::factory()
            ->count($count)
            ->make($attributes);
    }

    /**
     * Create a single user which is not saved yet.
     *
     * @param array $attributes
     *
     * @return \Tests\App\Models\User
     */
    public function makeOne(array $attributes = []): User
    {
        return User::factory()->make($attributes);
    }

    /**
     * Create a single user and save it in the database.
     *
     * @param array $attributes
     *
     * @return \Tests\App\Models\User
     */
    public function createOne(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Create a user, save it and then change its attributes in memory.
     *
     * The saved row gets an old created at and updated at, so a test can see later
     * whether the package has written the timestamps again. The recently created flag
     * is reset as well.
     *
     * After that the method copies the attributes of a new user into the model without
     * saving them. The returned model is therefore dirty and differs from its row.
     *
     * @param array $creatingAttributes
     * @param array $dirtyAttributes
     *
     * @return \Tests\App\Models\User
     */
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

    /**
     * Create a collection of users which are saved but dirty.
     *
     * Every user of the collection is built by {@see createOneAndDirty()}.
     *
     * @param int $count
     * @param array $creatingAttributes
     * @param array $dirtyAttributes
     *
     * @return \Tests\App\Collection\UserCollection
     */
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

    /**
     * Create a collection of users and save all of them in the database.
     *
     * @param int $count
     * @param array $creatingAttributes
     *
     * @return \Tests\App\Collection\UserCollection
     */
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
