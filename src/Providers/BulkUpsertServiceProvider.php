<?php

namespace Lapaliv\BulkUpsert\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Lapaliv\BulkUpsert\BulkDatabaseDriverManager;
use Lapaliv\BulkUpsert\Database\Drivers\BulkMysqlBulkDatabaseDriver;
use Lapaliv\BulkUpsert\Database\Drivers\BulkPostgresBulkDatabaseDriver;

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
        BulkDatabaseDriverManager::registerDriver(
            'mysql',
            $this->app->make(BulkMysqlBulkDatabaseDriver::class)
        );

        BulkDatabaseDriverManager::registerDriver(
            'pgsql',
            $this->app->make(BulkPostgresBulkDatabaseDriver::class)
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
