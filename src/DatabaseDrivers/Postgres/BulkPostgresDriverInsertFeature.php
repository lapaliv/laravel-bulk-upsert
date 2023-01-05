<?php

namespace Lapaliv\BulkUpsert\DatabaseDrivers\Postgres;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Features\BulkPrepareValuesForInsertingFeature;
use Throwable;

class BulkPostgresDriverInsertFeature
{
    public function __construct(
        private ConnectionInterface $connection,
        private string              $connectionName,
        private string              $table,
        private array               $selectColumns,
    )
    {
        //
    }

    /**
     * @param string[] $fields
     * @param array<int, array<string, scalar>> $rows
     * @param bool $ignoring
     * @return \stdClass[]
     * @throws \Throwable
     */
    public function handle(array $fields, array $rows, bool $ignoring): array
    {
        $prepareValuesFeature = new BulkPrepareValuesForInsertingFeature();
        [
            'bindings' => $bindings,
            'values' => $values,
        ] = $prepareValuesFeature->handle($fields, $rows);

        DB::connection($this->connectionName)->beginTransaction();

        try {
            $result = $this->connection->select(
                sprintf(
                    "INSERT INTO %s (%s) VALUES %s %s RETURNING %s;",
                    $this->table,
                    implode(',', $fields),
                    implode(',', $values),
                    $ignoring ? 'ON CONFLICT DO NOTHING' : '',
                    implode(',', $this->selectColumns)
                ),
                $bindings
            );

            DB::connection($this->connectionName)->commit();

            return $result;
        } catch (Throwable $throwable) {
            DB::connection($this->connectionName)->rollBack();

            throw $throwable;
        }
    }
}
