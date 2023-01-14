<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\BulkDatabaseDriverManager;
use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\Driver;
use Lapaliv\BulkUpsert\Exceptions\BulkDatabaseDriverIsNotSupported;

class BulkGetDriverFeature
{
    /**
     * @param BulkModel $model
     * @param scalar[][] $rows
     * @param string[] $uniqueColumns
     * @param string[] $selectColumns
     * @return BulkDatabaseDriver
     */
    public function handle(
        BulkModel $model,
//        array $rows,
//        array $uniqueColumns,
//        array $selectColumns,
    ): Driver
    {
        $driverName = $model->getConnection()->getDriverName();
        $driver = BulkDatabaseDriverManager::get($driverName);

        if ($driver === null) {
            throw new BulkDatabaseDriverIsNotSupported($driverName);
        }

        return $driver;
//
//        return $driver
//            ->setBuilder($model->newQuery())
//            ->setRows($rows)
//            ->setUniqueAttributes($uniqueColumns)
//            ->setHasIncrementing($model->getIncrementing())
//            ->setPrimaryKeyName($model->getKeyName())
//            ->setSelectColumns($selectColumns);
    }
}
