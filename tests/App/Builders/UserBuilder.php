<?php

namespace Tests\App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\BulkBuilderTrait;

/**
 * The query builder of the {@see \Tests\App\Models\User} test model.
 *
 * It extends the plain Eloquent builder and takes the bulk methods from the trait.
 * Most of the tests build their queries with it.
 *
 * @internal
 *
 * @method \Tests\App\Models\User firstOrFail($columns = ['*'])
 * @method \Tests\App\Builders\UserBuilder onlyTrashed()
 * @method \Tests\App\Builders\UserBuilder withTrashed()
 */
final class UserBuilder extends Builder
{
    use BulkBuilderTrait;
}
