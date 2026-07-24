<?php

namespace Tests\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Tests\App\Builders\CommentBuilder;
use Tests\App\Builders\PostBuilder;
use Tests\App\Collection\PostCollection;
use Tests\App\Factories\PostFactory;
use Tests\App\Traits\GlobalTouches;

/**
 * A simple test model with a text column and the usual timestamps.
 *
 * The post has many comments and no soft deletes. The tests use it as the related
 * model of a comment and as a model without any special feature.
 *
 * @internal
 *
 * @property int $id
 * @property string $text
 * @property \Carbon\CarbonInterface $created_at
 * @property \Carbon\CarbonInterface $updated_at
 *
 * @property-read \Tests\App\Models\Comment $comment
 * @property-read \Tests\App\Collection\CommentCollection $comments
 *
 * @method static \Tests\App\Builders\PostBuilder query()
 * @method static \Tests\App\Factories\PostFactory factory($count = null, $state = [])
 */
class Post extends Model
{
    use HasFactory;
    use GlobalTouches;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'posts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'text',
    ];

    /**
     * Create the table of the model for the tests.
     *
     * @return void
     */
    public static function createTable(): void
    {
        self::getSchema()->create(self::table(), function (Blueprint $table): void {
            $table->id();

            $table->string('text');

            $table->timestamps();
        });
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Tests\App\Builders\PostBuilder
     */
    public function newEloquentBuilder($query): PostBuilder
    {
        return new PostBuilder($query);
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param \Tests\App\Models\Post[] $models
     *
     * @return \Tests\App\Collection\PostCollection
     */
    public function newCollection(array $models = []): PostCollection
    {
        return new PostCollection($models);
    }

    /**
     * Get the comments which belong to the post.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany|\Tests\App\Builders\CommentBuilder
     */
    public function comments(): HasMany|CommentBuilder
    {
        return $this->hasMany(Comment::class, 'post_id', 'id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Tests\App\Factories\PostFactory
     */
    public static function newFactory(): PostFactory
    {
        return new PostFactory();
    }
}
