<?php

namespace Tests\App\Collection;

use Illuminate\Database\Eloquent\Collection;

/**
 * The collection which holds {@see \Tests\App\Models\User} test models.
 *
 * The User model returns it from `newCollection()`. The tests build their sets of users
 * with it and pass them to the bulk methods.
 *
 * @internal
 *
 * @method \ArrayIterator|\Tests\App\Models\User[] getIterator()
 * @method \Tests\App\Models\User|null first(callable $callback = null, $default = null)
 * @method \Tests\App\Models\User|null last(callable $callback = null, $default = null)
 * @method \Tests\App\Models\User|null get($key, $default = null)
 */
final class UserCollection extends Collection
{
    //
}
