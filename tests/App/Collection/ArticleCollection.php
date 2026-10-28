<?php

namespace Tests\App\Collection;

use Illuminate\Database\Eloquent\Collection;

/**
 * The collection which holds {@see \Tests\App\Models\Article} test models.
 *
 * The Article model returns it from `newCollection()`. The tests use it to check that
 * the package keeps the custom collection class of a model.
 *
 * @internal
 *
 * @method \ArrayIterator|\Tests\App\Models\Article[] getIterator()
 * @method \Tests\App\Models\Article|null first(callable $callback = null, $default = null)
 * @method \Tests\App\Models\Article|null last(callable $callback = null, $default = null)
 * @method \Tests\App\Models\Article|null get($key, $default = null)
 */
final class ArticleCollection extends Collection
{
    //
}
