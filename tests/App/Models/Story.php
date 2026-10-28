<?php

namespace Tests\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Tests\App\Builders\StoryBuilder;
use Tests\App\Collection\StoryCollection;
use Tests\App\Factories\StoryFactory;

/**
 * A test model with a uuid key and without any relation.
 *
 * The story uses soft deletes. The tests use it for the cases where the package has to
 * give the written models back.
 *
 * @property string $uuid
 * @property string $title
 * @property string $content
 * @property \Carbon\CarbonInterface $created_at
 * @property \Carbon\CarbonInterface $updated_at
 * @property \Carbon\CarbonInterface|null $deleted_at
 *
 * @method static \Tests\App\Factories\StoryFactory factory($count = null, $state = [])
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

    /**
     * Create the table of the model for the tests.
     *
     * The primary key is a uuid and the table also has a deleted at column.
     *
     * @return void
     */
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

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Tests\App\Builders\StoryBuilder
     */
    public function newEloquentBuilder($query): StoryBuilder
    {
        return new StoryBuilder($query);
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param \Tests\App\Models\Story[] $models
     *
     * @return \Tests\App\Collection\StoryCollection
     */
    public function newCollection(array $models = []): StoryCollection
    {
        return new StoryCollection($models);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Tests\App\Factories\StoryFactory
     */
    public static function newFactory(): StoryFactory
    {
        return new StoryFactory();
    }
}
