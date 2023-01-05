<?php

namespace Lapaliv\BulkUpsert\Providers;

use Illuminate\Support\ServiceProvider;
use Lapaliv\BulkUpsert\BulkUpsert;
use Lapaliv\BulkUpsert\DatabaseDrivers\BulkMysqlBulkDatabaseDriver;
use Lapaliv\BulkUpsert\DatabaseDrivers\BulkPostgresBulkDatabaseDriver;

class BulkUpsertServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        BulkUpsert::registerDatabaseDriver('mysql', new BulkMysqlBulkDatabaseDriver());
        BulkUpsert::registerDatabaseDriver('pgsql', new BulkPostgresBulkDatabaseDriver());
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
