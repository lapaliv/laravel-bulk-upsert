<?php

namespace Tests\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Tests\App\Builders\ArticleBuilder;
use Tests\App\Collection\ArticleCollection;
use Tests\App\Factories\ArticleFactory;
use Tests\App\Traits\GlobalTouches;

/**
 * A test model whose primary key is a uuid.
 *
 * The tests use the article to check the bulk methods on a model which has no auto
 * incrementing key. It also uses soft deletes and belongs to a user.
 *
 * @internal
 *
 * @property-read string $uuid
 * @property-read int $user_id
 * @property-read string $title
 * @property-read \Carbon\CarbonInterface $created_at
 * @property-read \Carbon\CarbonInterface $updated_at
 * @property-read \Carbon\CarbonInterface|null $deleted_at
 * @property-read \Tests\App\Models\User $user
 *
 * @method static \Tests\App\Factories\ArticleFactory factory($count = null, $state = [])
 */
class Article extends Model
{
    use SoftDeletes;
    use HasFactory;
    use GlobalTouches;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    public $primaryKey = 'uuid';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'articles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'title',
    ];

    /**
     * Create the table of the model for the tests.
     *
     * The primary key is a uuid and the user id points to the users table with a
     * cascade on update and on delete.
     *
     * @return void
     */
    public static function createTable(): void
    {
        self::getSchema()->create(self::table(), function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('title');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Tests\App\Builders\ArticleBuilder
     */
    public function newEloquentBuilder($query): ArticleBuilder
    {
        return new ArticleBuilder($query);
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param \Tests\App\Models\Article[] $models
     *
     * @return \Tests\App\Collection\ArticleCollection
     */
    public function newCollection(array $models = []): ArticleCollection
    {
        return new ArticleCollection($models);
    }

    /**
     * Get the user who has written the article.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Tests\App\Factories\ArticleFactory
     */
    public static function newFactory(): ArticleFactory
    {
        return new ArticleFactory();
    }
}
