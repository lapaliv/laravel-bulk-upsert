<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;

/**
 * @internal
 *
 * @method MySqlUser|UserCollection create($attributes = [], ?Model $parent = null)
 * @method MySqlUser|UserCollection make($attributes = [], ?Model $parent = null)
 * @method MySqlUser|UserCollection createMany(iterable $records)
 */
final class MySqlUserFactory extends UserFactory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<MySqlUser>
     */
    protected $model = MySqlUser::class;
}
