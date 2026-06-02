<?php

namespace Tests\App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Tests\App\Builders\StoryBuilder;
use Tests\App\Collection\StoryCollection;
use Tests\App\Factories\StoryFactory;

/**
 * @property string $uuid
 * @property string $title
 * @property string $content
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 *
 * @method static StoryFactory factory($count = null, $state = [])
 */
class Story extends Model
{
    use SoftDeletes;
    use HasFactory;

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
    protected $table = 'stories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'uuid',
        'title',
        'content',
    ];

    public static function createTable(): void
    {
        self::getSchema()->create(self::table(), function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->string('title');
            $table->text('content');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function newEloquentBuilder($query): StoryBuilder
    {
        return new StoryBuilder($query);
    }

    public function newCollection(array $models = []): StoryCollection
    {
        return new StoryCollection($models);
    }

    public static function newFactory(): StoryFactory
    {
        return new StoryFactory();
    }
}
