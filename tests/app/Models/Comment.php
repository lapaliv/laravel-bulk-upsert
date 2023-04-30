<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Lapaliv\BulkUpsert\Tests\App\Builders\CommentBuilder;
use Lapaliv\BulkUpsert\Tests\App\Builders\PostBuilder;
use Lapaliv\BulkUpsert\Tests\App\Builders\UserBuilder;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Traits\GlobalTouches;

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
 * @method static CommentBuilder query()
 */
abstract class Comment extends Model
{
    use HasFactory;
    use GlobalTouches;

    protected $table = 'comments';

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

    abstract public function user(): BelongsTo|UserBuilder;

    abstract public function post(): BelongsTo|PostBuilder;
}
