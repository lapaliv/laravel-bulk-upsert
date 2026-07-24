<?php

namespace Lapaliv\BulkUpsert\Providers;

use Illuminate\Support\ServiceProvider;
use Lapaliv\BulkUpsert\Builders\WhereClauseApplier;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Converters\MixedValueToScalarConverter;
use Lapaliv\BulkUpsert\Drivers\DriverManager;
use Lapaliv\BulkUpsert\Drivers\MySqlBulkDriver;
use Lapaliv\BulkUpsert\Drivers\PostgreSqlBulkDriver;
use Lapaliv\BulkUpsert\Drivers\SqLiteBulkDriver;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Support\ModelMetadata;
use Lapaliv\BulkUpsert\Support\UniqueKeyGenerator;
use Lapaliv\BulkUpsert\Support\ValueHasher;

/**
 * Registers the package services in the container.
 *
 * @psalm-suppress UnusedClass
 */
class BulkUpsertServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * The method binds the helpers of the package as singletons, registers the bulk
     * drivers for `mysql`, `pgsql` and `sqlite`, and gives the application event
     * dispatcher to {@see \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher}.
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function register(): void
    {
        $mixedValueToScalarConverter = new MixedValueToScalarConverter();
        $valueHasher = new ValueHasher($mixedValueToScalarConverter);
        $uniqueKeyGenerator = new UniqueKeyGenerator($valueHasher, $mixedValueToScalarConverter);

        $this->app->singleton(BulkDriverManager::class, fn() => new DriverManager());
        $this->app->singleton(ModelMetadata::class, fn() => new ModelMetadata());
        $this->app->singleton(ValueHasher::class, fn() => $valueHasher);
        $this->app->singleton(
            WhereClauseApplier::class,
            fn() => new WhereClauseApplier($valueHasher)
        );
        $this->app->singleton(
            UniqueKeyGenerator::class,
            fn() => $uniqueKeyGenerator
        );

        /** @var \Lapaliv\BulkUpsert\Drivers\DriverManager $driverManager */
        $driverManager = $this->app->make(BulkDriverManager::class);
        $driverManager->registerDriver(
            'mysql',
            $this->app->make(MySqlBulkDriver::class)
        );
        $driverManager->registerDriver(
            'pgsql',
            $this->app->make(PostgreSqlBulkDriver::class)
        );
        $driverManager->registerDriver(
            'sqlite',
            $this->app->make(SqLiteBulkDriver::class)
        );

        /** @var \Illuminate\Contracts\Events\Dispatcher $eventDispatcher */
        $eventDispatcher = $this->app->make('events');

        BulkEventDispatcher::setIlluminateEventDispatcher($eventDispatcher);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
