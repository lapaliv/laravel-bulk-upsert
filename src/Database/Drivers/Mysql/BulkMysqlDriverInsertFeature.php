<?php

namespace Lapaliv\BulkUpsert\Database\Drivers\Mysql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Features\BulkPrepareValuesForInsertingFeature;
use Throwable;

class BulkMysqlDriverInsertFeature
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
     * @param bool $hasIncrementing
     * @return int|null
     */
    public function handle(
        ConnectionInterface $connection,
        string $table,
        array $fields,
        array $rows,
        bool $ignore,
        bool $hasIncrementing,
    ): ?int
    {
        [
            'bindings' => $bindings,
            'values' => $values
        ] = $this->prepareValuesForInsertingFeature->handle($fields, $rows);

        $connection->beginTransaction();

        try {
            $connection->insert(
                sprintf(
                    "INSERT %s INTO %s (%s) VALUES %s;",
                    $ignore ? 'IGNORE' : '',
                    $table,
                    implode(',', $fields),
                    implode(',', $values),
                ),
                $bindings
            );

            $lastInsertedId = $hasIncrementing
                ? $connection->selectOne("SELECT LAST_INSERT_ID() as payload;")
                : null;

            $connection->commit();

            return $lastInsertedId?->payload;
        } catch (Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }
    }
}
