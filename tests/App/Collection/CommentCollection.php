<?php

namespace Tests\App\Collection;

use Illuminate\Database\Eloquent\Collection;

/**
 * The collection which holds Comment test models.
 *
 * The Comment model returns it from `newCollection()`. The tests use it to check that
 * the package keeps the custom collection class of a model.
 *
 * @internal
 */
final class CommentCollection extends Collection
{
    //
}
