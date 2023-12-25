<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Lapaliv\BulkUpsert\Tests\App\Builders\UserBuilder;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Enums\Gender;
use Lapaliv\BulkUpsert\Tests\App\Traits\GlobalTouches;

/**
 * @internal
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Gender $gender
 * @property string|null $avatar
 * @property int $posts_count
 * @property bool $is_admin
 * @property float|null $balance
 * @property CarbonInterface|null $birthday
 * @property array|null $phones
 * @property CarbonInterface|null $last_visited_at
 * @property string $update_uuid
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 *
 * @method static UserBuilder query()
 */
abstract class User extends Model
{
    use SoftDeletes;
    use HasFactory;
    use GlobalTouches;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'email',
        'gender',
        'avatar',
        'posts_count',
        'is_admin',
        'balance',
        'birthday',
        'phones',
        'last_visited_at',
        'update_uuid',
    ];

    protected $casts = [
        'gender' => Gender::class,
        'posts_count' => 'integer',
        'is_admin' => 'boolean',
        'balance' => 'float',
        'birthday' => 'date:Y-m-d',
        'phones' => 'array',
        'last_visited_at' => 'datetime:Y-m-d H:i:s',
    ];

    public static function createTable(): void
    {
        self::getSchema()->create(self::table(), function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('email')
                ->unique()
                ->nullable();
            $table->string('gender', 6);
            $table->string('avatar')
                ->nullable();
            $table->unsignedInteger('posts_count');
            $table->boolean('is_admin');
            $table->float('balance')
                ->nullable();
            $table->date('birthday')
                ->nullable();
            $table->json('phones')
                ->nullable();
            $table->dateTime('last_visited_at')
                ->nullable();
            $table->uuid('update_uuid')
                ->unique()
                ->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function newEloquentBuilder($query): UserBuilder
    {
        return new UserBuilder($query);
    }

    public function newCollection(array $models = []): UserCollection
    {
        return new UserCollection($models);
    }
}
