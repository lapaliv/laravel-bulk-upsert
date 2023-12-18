<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteUser;

/**
 * @internal
 *
 * @method SqLiteUser|UserCollection create($attributes = [], ?Model $parent = null)
 * @method SqLiteUser|UserCollection make($attributes = [], ?Model $parent = null)
 * @method SqLiteUser|UserCollection createMany(iterable $records)
 */
final class SqLiteUserFactory extends UserFactory
{
    protected $model = SqLiteUser::class;
}
