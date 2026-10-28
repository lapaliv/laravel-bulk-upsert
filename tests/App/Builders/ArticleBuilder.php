<?php

namespace Tests\App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\BulkBuilderTrait;

/**
 * The query builder of the {@see \Tests\App\Models\Article} test model.
 *
 * It extends the plain Eloquent builder and takes the bulk methods from the trait.
 * The tests use it to check that the package also works with a custom builder class.
 *
 * @internal
 *
 * @method \Tests\App\Models\Article firstOrFail($columns = ['*'])
 * @method \Tests\App\Builders\ArticleBuilder onlyTrashed()
 * @method \Tests\App\Builders\ArticleBuilder withTrashed()
 */
final class ArticleBuilder extends Builder
{
    use BulkBuilderTrait;
}
