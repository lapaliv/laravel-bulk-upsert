<?php

namespace Lapaliv\BulkUpsert\DatabaseDrivers\Postgres;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Features\BulkPrepareValuesForInsertingFeature;
use Throwable;

class BulkPostgresDriverInsertFeature
{
    public function __construct(
        private Connection $connection,
        private string     $table,
        private array      $uniqueAttributes,
        private ?string    $primaryKey = null,
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

        $selectColumns = array_unique(
            array_filter(
                array_merge($this->uniqueAttributes, [$this->primaryKey])
            )
        );

        DB::connection($this->connection->getName())->beginTransaction();

        try {
            $result = $this->connection->select(
                sprintf(
                    "INSERT INTO %s (%s) VALUES %s %s RETURNING %s;",
                    $this->table,
                    implode(',', $fields),
                    implode(',', $values),
                    $ignoring ? 'ON CONFLICT DO NOTHING' : '',
                    implode(',', $selectColumns)
                ),
                $bindings
            );

            DB::connection($this->connection->getName())->commit();

            return $result;
        } catch (Throwable $throwable) {
            DB::connection($this->connection->getName())->rollBack();

            throw $throwable;
        }
    }
}