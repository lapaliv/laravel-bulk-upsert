<?php

namespace Tests\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Tests\App\Builders\CommentBuilder;
use Tests\App\Builders\PostBuilder;
use Tests\App\Builders\UserBuilder;
use Tests\App\Collection\CommentCollection;
use Tests\App\Factories\CommentFactory;
use Tests\App\Traits\GlobalTouches;

/**
 * A test model which belongs to a user and to a post.
 *
 * The tests use the comment mostly to check that the package touches the related
 * models. The comment has no soft deletes.
 *
 * @internal
 *
 * @property int $id
 * @property int $user_id
 * @property string $text
 * @property \Carbon\CarbonInterface $created_at
 * @property \Carbon\CarbonInterface $updated_at
 *
 * @property-read \Tests\App\Models\User $user
 * @property-read \Tests\App\Models\Post $post
 *
 * @method static \Tests\App\Factories\CommentFactory factory($count = null, $state = [])
 * @method static \Tests\App\Builders\CommentBuilder query()
 */
class Comment extends Model
{
    use HasFactory;
    use GlobalTouches;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'comments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'post_id',
        'text',
    ];

    /**
     * Create the table of the model for the tests.
     *
     * The user id and the post id both point to their tables. They cascade on update
     * and do nothing on delete.
     *
     * @return void
     */
    public static function createTable(): void
    {
        self::getSchema()->create(self::table(), function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('post_id');
            $table->string('text');

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->noActionOnDelete();
            $table->foreign('post_id')
                ->references('id')
                ->on('posts')
                ->cascadeOnUpdate()
                ->noActionOnDelete();
        });
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Tests\App\Builders\CommentBuilder
     */
    public function newEloquentBuilder($query): CommentBuilder
    {
        return new CommentBuilder($query);
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param \Tests\App\Models\Comment[] $models
     *
     * @return \Tests\App\Collection\CommentCollection
     */
    public function newCollection(array $models = []): CommentCollection
    {
        return new CommentCollection($models);
    }

    /**
     * Get the user who has written the comment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo|\Tests\App\Builders\UserBuilder
     */
    public function user(): BelongsTo|UserBuilder
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the post which the comment belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo|\Tests\App\Builders\PostBuilder
     */
    public function post(): BelongsTo|PostBuilder
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Tests\App\Factories\CommentFactory
     */
    public static function newFactory(): CommentFactory
    {
        return new CommentFactory();
    }
}
