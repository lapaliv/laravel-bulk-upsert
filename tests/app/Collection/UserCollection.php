<?php

namespace Lapaliv\BulkUpsert\Tests\App\Collection;

use ArrayIterator;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Tests\App\Models\User;

/**
 * @internal
 *
 * @method ArrayIterator|User[] getIterator()
 */
class UserCollection extends Collection
{
    //
}
