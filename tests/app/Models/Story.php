<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Lapaliv\BulkUpsert\Tests\App\Builders\StoryBuilder;
use Lapaliv\BulkUpsert\Tests\App\Collection\StoryCollection;

/**
 * @property string $uuid
 * @property string $title
 * @property string $content
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 */
abstract class Story extends Model
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
}
