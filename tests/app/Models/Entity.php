<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Schema\Blueprint;
use Lapaliv\BulkUpsert\Tests\App\Collections\EntityCollection;

/**
 * @property string $uuid
 * @property string $string
 * @property string|null $nullable_string
 * @property int $integer
 * @property int|null $nullable_integer
 * @property float $decimal
 * @property float|null $nullable_decimal
 * @property bool $boolean
 * @property bool|null $nullable_boolean
 * @property array $json
 * @property array|null $nullable_json
 * @property CarbonInterface $date
 * @property CarbonInterface|null $nullable_date
 * @property CarbonInterface $custom_datetime
 * @property CarbonInterface|null $nullable_custom_datetime
 * @property CarbonInterface $microseconds
 * @property CarbonInterface|null $nullable_microseconds
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
abstract class Entity extends Model
{
    public const CUSTOM_DATE_FORMAT = 'H:i:s d.m.Y';
    public const MICROSECONDS_FORMAT = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'uuid',
        'string',
        'nullable_string',
        'integer',
        'nullable_integer',
        'decimal',
        'nullable_decimal',
        'boolean',
        'nullable_boolean',
        'json',
        'nullable_json',
        'date',
        'nullable_date',
        'custom_datetime',
        'nullable_custom_datetime',
        'microseconds',
        'nullable_microseconds',
    ];

    protected $casts = [
        'integer' => 'integer',
        'nullable_integer' => 'integer',
        'decimal' => 'float',
        'nullable_decimal' => 'float',
        'boolean' => 'boolean',
        'nullable_boolean' => 'boolean',
        'json' => 'json',
        'nullable_json' => 'json',
        'date' => 'date',
        'nullable_date' => 'date',
        'custom_datetime' => 'datetime:' . self::CUSTOM_DATE_FORMAT,
        'nullable_custom_datetime' => 'datetime:' . self::CUSTOM_DATE_FORMAT,
        'microseconds' => 'datetime:' . self::MICROSECONDS_FORMAT,
        'nullable_microseconds' => 'datetime:' . self::MICROSECONDS_FORMAT,
    ];

    public static function createTable(): void
    {
        $table = (new static())->getTable();
        $hasIncrementing = (new static())->getIncrementing();

        self::getSchema()->dropIfExists($table);
        self::getSchema()->create($table, function (Blueprint $table) use ($hasIncrementing): void {
            if ($hasIncrementing) {
                $table->id();
                $table->uuid()->unique();
            } else {
                $table->uuid()->primary();
            }

            $table->string('string');
            $table->string('nullable_string')
                ->nullable();
            $table->integer('integer');
            $table->integer('nullable_integer')
                ->nullable();
            $table->decimal('decimal', 8, 3);
            $table->decimal('nullable_decimal', 8, 3)
                ->nullable();
            $table->boolean('boolean');
            $table->boolean('nullable_boolean')
                ->nullable();
            $table->json('json');
            $table->json('nullable_json')
                ->nullable();
            $table->date('date');
            $table->date('nullable_date')
                ->nullable();
            $table->string('custom_datetime');
            $table->string('nullable_custom_datetime')
                ->nullable();
            $table->timestamp('microseconds', 6);
            $table->timestamp('nullable_microseconds', 6)
                ->nullable();

            $table->timestamps();
        });
    }

    public function newCollection(array $models = []): EntityCollection
    {
        return new EntityCollection($models);
    }
}
