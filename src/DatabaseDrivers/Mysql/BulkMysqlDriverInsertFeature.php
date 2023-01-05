<?php

namespace Lapaliv\BulkUpsert\DatabaseDrivers\Mysql;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Features\BulkPrepareValuesForInsertingFeature;
use Throwable;

class BulkMysqlDriverInsertFeature
{
    public function __construct(
        private ConnectionInterface $connection,
        private string              $connectionName,
        private string              $table,
        private bool                $hasIncrementing
    )
    {
        //
    }

    /**
     * @param string[] $fields
     * @param array<int, array<string, scalar>> $rows
     * @param bool $ignore
     * @return int|null
     * @throws \Throwable
     */
    public function handle(array $fields, array $rows, bool $ignore): ?int
    {
        $preparingValuesFeature = new BulkPrepareValuesForInsertingFeature();

        [
            'bindings' => $bindings,
            'values' => $values
        ] = $preparingValuesFeature->handle($fields, $rows);

        DB::connection($this->connectionName)->beginTransaction();

        try {
            $this->connection->insert(
                sprintf(
                    "INSERT %s INTO %s (%s) VALUES %s;",
                    $ignore ? 'IGNORE' : '',
                    $this->table,
                    implode(',', $fields),
                    implode(',', $values),
                ),
                $bindings
            );

            $lastInsertedId = $this->hasIncrementing
                ? $this->connection->selectOne("SELECT LAST_INSERT_ID() as payload;")
                : null;

            DB::connection($this->connectionName)->commit();

            return $lastInsertedId?->payload;
        } catch (Throwable $throwable) {
            DB::connection($this->connectionName)->rollBack();
            throw $throwable;
        }
    }
}
