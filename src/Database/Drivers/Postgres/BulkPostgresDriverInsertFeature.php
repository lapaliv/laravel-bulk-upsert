<?php

namespace Lapaliv\BulkUpsert\Database\Drivers\Postgres;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Features\BulkPrepareValuesForInsertingFeature;
use stdClass;
use Throwable;

class BulkPostgresDriverInsertFeature
{
    public function __construct(
        private BulkPrepareValuesForInsertingFeature $prepareValuesForInsertingFeature,
    )
    {
        //
    }

    /**
     * @param ConnectionInterface $connection
     * @param string $table
     * @param string[] $fields
     * @param array<int, array<string, scalar>> $rows
     * @param bool $ignore
     * @param array $selectColumns
     * @return stdClass[]
     */
    public function handle(
        ConnectionInterface $connection,
        string $table,
        array $fields,
        array $rows,
        bool $ignore,
        array $selectColumns,
    ): array
    {
        [
            'bindings' => $bindings,
            'values' => $values,
        ] = $this->prepareValuesForInsertingFeature->handle($fields, $rows);

        $connection->beginTransaction();

        try {
            $result = $connection->select(
                sprintf(
                    "INSERT INTO %s (%s) VALUES %s %s RETURNING %s;",
                    $table,
                    implode(',', $fields),
                    implode(',', $values),
                    $ignore ? 'ON CONFLICT DO NOTHING' : '',
                    implode(',', $selectColumns)
                ),
                $bindings
            );

            $connection->commit();

            return $result;
        } catch (Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }
}
