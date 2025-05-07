<?php

namespace Tests\App\Models;

use Carbon\CarbonInterface;
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
 * @internal
 *
 * @property int $id
 * @property int $user_id
 * @property string $text
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 *
 * @property-read User $user
 * @property-read Post $post
 *
 * @method static CommentFactory factory($count = null, $state = [])
 * @method static CommentBuilder query()
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

    public function newEloquentBuilder($query): CommentBuilder
    {
        return new CommentBuilder($query);
    }

    public function newCollection(array $models = []): CommentCollection
    {
        return new CommentCollection($models);
    }

    public function user(): BelongsTo|UserBuilder
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function post(): BelongsTo|PostBuilder
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    public static function newFactory(): CommentFactory
    {
        return new CommentFactory();
    }
}
