<?php

namespace Tests\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Tests\App\Builders\ArticleBuilder;
use Tests\App\Builders\CommentBuilder;
use Tests\App\Builders\UserBuilder;
use Tests\App\Collection\UserCollection;
use Tests\App\Enums\Gender;
use Tests\App\Factories\UserFactory;
use Tests\App\Traits\GlobalTouches;

/**
 * The main test model of the suite.
 *
 * The user carries a column of almost every type, a custom cast, a custom builder and
 * a custom collection. It also uses soft deletes. Most of the tests of the package are
 * written against this model.
 *
 * @internal
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Tests\App\Enums\Gender $gender
 * @property string|null $avatar
 * @property int $posts_count
 * @property bool $is_admin
 * @property float|null $balance
 * @property \Carbon\CarbonInterface|null $birthday
 * @property array|null $phones
 * @property \Carbon\CarbonInterface|null $last_visited_at
 * @property string $update_uuid
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property \Carbon\CarbonInterface|null $deleted_at
 *
 * @method static \Tests\App\Builders\UserBuilder query()
 *
 * @property-read \Tests\App\Collection\CommentCollection $comments
 * @property-read \Tests\App\Collection\ArticleCollection $articles
 *
 * @method static \Tests\App\Factories\UserFactory factory($count = null, $state = [])
 */
class User extends Model
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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'gender' => Gender::class,
        'posts_count' => 'integer',
        'is_admin' => 'boolean',
        'balance' => 'float',
        'birthday' => 'date:Y-m-d',
        'phones' => 'array',
        'last_visited_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Create the table of the model for the tests.
     *
     * The table holds a column of almost every type, so the tests can check how the
     * package writes and reads them. The email and the update uuid are unique, which
     * lets the tests use them as the unique attributes of an upsert.
     *
     * @return void
     */
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

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Tests\App\Builders\UserBuilder
     */
    public function newEloquentBuilder($query): UserBuilder
    {
        return new UserBuilder($query);
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param \Tests\App\Models\User[] $models
     *
     * @return \Tests\App\Collection\UserCollection
     */
    public function newCollection(array $models = []): UserCollection
    {
        return new UserCollection($models);
    }

    /**
     * Get the comments which the user has written.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany|\Tests\App\Builders\CommentBuilder
     */
    public function comments(): HasMany|CommentBuilder
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    /**
     * Get the articles which the user has written.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany|\Tests\App\Builders\ArticleBuilder
     */
    public function articles(): HasMany|ArticleBuilder
    {
        return $this->hasMany(Article::class, 'user_id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Tests\App\Factories\UserFactory
     */
    protected static function newFactory(): UserFactory
    {
        return new UserFactory();
    }
}
