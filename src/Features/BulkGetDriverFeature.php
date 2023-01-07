<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\BulkDatabaseDriverManager;
use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Exceptions\BulkDatabaseDriverIsNotSupported;

class BulkGetDriverFeature
{
    public function handle(
        BulkModel $model,
        array $rows,
        array $uniqueColumns,
        array $selectColumns,
    ): BulkDatabaseDriver
    {
        $driverName = $model->getConnection()->getDriverName();
        $driver = BulkDatabaseDriverManager::get($driverName);

        if ($driver === null) {
            throw new BulkDatabaseDriverIsNotSupported($driverName);
        }

        return $driver
            ->setBuilder($model->newQuery())
            ->setRows($rows)
            ->setUniqueAttributes($uniqueColumns)
            ->setHasIncrementing($model->getIncrementing())
            ->setPrimaryKeyName($model->getKeyName())
            ->setSelectColumns($selectColumns);
    }
}
