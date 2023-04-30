<?php

namespace Lapaliv\BulkUpsert\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Lapaliv\BulkUpsert\BulkBulkDriverManager;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Drivers\MySqlBulkDriver;

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
        $this->app->singleton(BulkDriverManager::class, fn () => new BulkBulkDriverManager());

        /** @var BulkBulkDriverManager $driverManager */
        $driverManager = $this->app->make(BulkDriverManager::class);
        $driverManager->registerDriver(
            'mysql',
            $this->app->make(MySqlBulkDriver::class)
        );
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
