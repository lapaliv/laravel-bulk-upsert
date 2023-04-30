<?php

namespace Lapaliv\BulkUpsert\Tests\App\Collection;

use ArrayIterator;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Tests\App\Models\User;

/**
 * @internal
 *
 * @method ArrayIterator|User[] getIterator()
 * @method User|null first(callable $callback = null, $default = null)
 * @method User|null last(callable $callback = null, $default = null)
 * @method User|null get($key, $default = null)
 */
final class UserCollection extends Collection
{
    //
}
