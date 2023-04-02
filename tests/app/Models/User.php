<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Enums\Gender;
use Lapaliv\BulkUpsert\Tests\App\Factories\UserFactory;

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
 * @property float $balance
 * @property CarbonInterface $birthday
 * @property array|null $phones
 * @property CarbonInterface $last_visited_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 *
 * @method static UserFactory factory($count = null, $state = [])
 */
abstract class User extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $table = 'users';

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
        self::getSchema()->dropIfExists(self::table());
        self::getSchema()->create(self::table(), function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('email')->unique();
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
            $table->timestamp('last_visited_at')
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
