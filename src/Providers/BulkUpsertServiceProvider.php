<?php

namespace Lapaliv\BulkUpsert\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Lapaliv\BulkUpsert\BulkDriverManager;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\BulkUpsert;
use Lapaliv\BulkUpsert\Contracts\BulkInsertContract;
use Lapaliv\BulkUpsert\Contracts\BulkUpdateContract;
use Lapaliv\BulkUpsert\Contracts\BulkUpsertContract;
use Lapaliv\BulkUpsert\Contracts\DriverManager;
use Lapaliv\BulkUpsert\Drivers\MySqlDriver;

class BulkUpsertServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->app->singleton(DriverManager::class, fn () => new BulkDriverManager());

        /** @var BulkDriverManager $driverManager */
        $driverManager = $this->app->make(DriverManager::class);
        $driverManager->registerDriver(
            'mysql',
            $this->app->make(MySqlDriver::class)
        );

        $this->app->bind(BulkInsertContract::class, BulkInsert::class);
        $this->app->bind(BulkUpdateContract::class, BulkUpdateContract::class);
        $this->app->bind(BulkUpsertContract::class, BulkUpsert::class);
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
