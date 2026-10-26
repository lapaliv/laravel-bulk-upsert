<?php

namespace Tests\App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

/**
 * A test model which lives inside a tenant.
 *
 * The email is unique only inside the tenant, and a global scope limits every query
 * to the current tenant. The tests use it to check that the package never writes
 * to the rows which the scope hides.
 *
 * @internal
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $email
 * @property string $name
 * @property string|null $bio
 * @property \Carbon\CarbonInterface $created_at
 * @property \Carbon\CarbonInterface $updated_at
 * @property \Carbon\CarbonInterface|null $deleted_at
 */
class Member extends Model
{
    use SoftDeletes;

    /**
     * The tenant which the global scope lets through.
     *
     * @var int
     */
    public const int CURRENT_TENANT = 1;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'members';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'email',
        'name',
        'bio',
    ];

    /**
     * Create the table of the model for the tests.
     *
     * @return void
     */
    public static function createTable(): void
    {
        self::getSchema()->create(self::table(), function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->string('email');
            $table->string('name');
            $table->text('bio')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
        });
    }

    /**
     * Limit every query to the current tenant.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::addGlobalScope(
            'tenant',
            fn (Builder $builder) => $builder->where(
                $builder->getModel()->qualifyColumn('tenant_id'),
                self::CURRENT_TENANT
            )
        );
    }
}
