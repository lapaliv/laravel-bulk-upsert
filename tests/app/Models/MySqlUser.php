<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Lapaliv\BulkUpsert\Tests\App\Collections\MySqlUserCollection;
use Lapaliv\BulkUpsert\Tests\App\Factories\MySqlUserFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string $email
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 *
 * @method static MySqlUserFactory factory($count = null, $state = [])
 */
class MySqlUser extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'users';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'deleted_at',
    ];

    public static function createTable(): void
    {
        $table = (new static())->getTable();

        self::getSchema()->dropIfExists($table);
        self::getSchema()->create($table, function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('phone')
                ->nullable();
            $table->string('email')
                ->unique();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public static function newFactory(): MySqlUserFactory
    {
        return new MySqlUserFactory();
    }

    public function newCollection(array $models = []): MySqlUserCollection
    {
        return new MySqlUserCollection($models);
    }
}
