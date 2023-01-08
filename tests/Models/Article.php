<?php

namespace Lapaliv\BulkUpsert\Tests\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Lapaliv\BulkUpsert\Tests\Collections\ArticleCollection;

/**
 * @property string $uuid
 * @property string $name
 * @property string|null $content
 * @property bool $is_new
 * @property CarbonInterface|null $date
 * @property CarbonInterface|null $microseconds
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 */
abstract class Article extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $table = 'articles';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    protected $fillable = [
        'uuid',
        'name',
        'content',
        'is_new',
        'date',
        'microseconds',
    ];

    protected $casts = [
        'is_new' => 'boolean',
    ];

    public function newCollection(array $models = []): ArticleCollection
    {
        return new ArticleCollection($models);
    }

    public static function dropTable(): void
    {
        self::getSchema()->dropIfExists('articles');
    }

    public static function createTable(): void
    {
        self::dropTable();
        self::getSchema()->create('articles', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('name', 50);
            $table->string('content')
                ->nullable();
            $table->boolean('is_new')
                ->default(true);
            $table->date('date')
                ->nullable();
            $table->timestamp('microseconds', 6)
                ->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }
}
