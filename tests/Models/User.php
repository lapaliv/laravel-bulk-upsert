<?php

namespace Lapaliv\BulkUpsert\Tests\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Lapaliv\BulkUpsert\Tests\Collections\UserCollection;

/**
 * @property int $id
 * @property string $email
 * @property string $name
 * @property string|null $phone
 * @property CarbonInterface|null $date
 * @property CarbonInterface|null $microseconds
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 */
abstract class User extends Model
{
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'email',
        'name',
        'phone',
        'date',
        'microseconds',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'microseconds' => 'date:Y-m-d H:i:s.u',
    ];

    public static function dropTable(): void
    {
        self::getSchema()->dropIfExists('users');
    }

    public static function createTable(): void
    {
        self::dropTable();
        self::getSchema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 100)
                ->unique();
            $table->string('name', 50);
            $table->string('phone')
                ->nullable();
            $table->date('date')
                ->nullable();
            $table->timestamp('microseconds', 6)
                ->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function newCollection(array $models = []): UserCollection
    {
        return new UserCollection($models);
    }
}
