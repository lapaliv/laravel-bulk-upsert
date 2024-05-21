<?php

namespace Lapaliv\BulkUpsert\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Lapaliv\BulkUpsert\BulkBulkDriverManager;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Drivers\MySqlBulkDriver;
use Lapaliv\BulkUpsert\Drivers\PostgreSqlBulkDriver;
use Lapaliv\BulkUpsert\Drivers\SqLiteBulkDriver;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Features\AddWhereClauseToBuilderFeature;
use Lapaliv\BulkUpsert\Features\GetDateFieldsFeature;
use Lapaliv\BulkUpsert\Features\GetDeletedAtColumnFeature;
use Lapaliv\BulkUpsert\Features\GetUniqueKeyFeature;
use Lapaliv\BulkUpsert\Features\GetValueHashFeature;
use Lapaliv\BulkUpsert\Features\KeyByFeature;

/**
 * @psalm-suppress UnusedClass
 */
class BulkUpsertServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     *
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $getValueHashFeature = new GetValueHashFeature();
        $getUniqueKeyFeature = new GetUniqueKeyFeature($getValueHashFeature);

        $this->app->singleton(BulkDriverManager::class, fn () => new BulkBulkDriverManager());
        $this->app->singleton(GetDateFieldsFeature::class, fn () => new GetDateFieldsFeature());
        $this->app->singleton(GetDeletedAtColumnFeature::class, fn () => new GetDeletedAtColumnFeature());
        $this->app->singleton(GetValueHashFeature::class, fn () => $getValueHashFeature);
        $this->app->singleton(
            AddWhereClauseToBuilderFeature::class,
            fn () => new AddWhereClauseToBuilderFeature($getValueHashFeature)
        );
        $this->app->singleton(
            GetUniqueKeyFeature::class,
            fn () => $getUniqueKeyFeature
        );
        $this->app->singleton(
            KeyByFeature::class,
            fn () => new KeyByFeature($getUniqueKeyFeature)
        );

        /** @var BulkBulkDriverManager $driverManager */
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

        BulkEventDispatcher::setIlluminateEventDispatcher($this->app->make('events'));
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
