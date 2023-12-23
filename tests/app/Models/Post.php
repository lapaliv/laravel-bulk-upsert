<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Lapaliv\BulkUpsert\Tests\App\Builders\CommentBuilder;
use Lapaliv\BulkUpsert\Tests\App\Builders\PostBuilder;
use Lapaliv\BulkUpsert\Tests\App\Collection\PostCollection;
use Lapaliv\BulkUpsert\Tests\App\Factories\PostFactory;
use Lapaliv\BulkUpsert\Tests\App\Traits\GlobalTouches;

/**
 * @internal
 *
 * @property int $id
 * @property string $text
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 *
 * @property-read Comment $comment
 *
 * @method static PostBuilder query()
 * @method static PostFactory factory($count = null, $state = [])
 */
abstract class Post extends Model
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

    public static function createTable(): void
    {
        self::getSchema()->create(self::table(), function (Blueprint $table): void {
            $table->id();

            $table->string('text');

            $table->timestamps();
        });
    }

    public function newEloquentBuilder($query): PostBuilder
    {
        return new PostBuilder($query);
    }

    public function newCollection(array $models = []): PostCollection
    {
        return new PostCollection($models);
    }

    abstract public function comments(): HasMany|CommentBuilder;
}
