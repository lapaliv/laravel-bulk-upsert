<?php

namespace Lapaliv\BulkUpsert\Drivers\PostgreSql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Entities\BulkPostgreSqlInsertResult;

class PostgreSqlDriverInsert
{
    public function __construct(
        private MixedValueToSqlConverter $mixedValueToSqlConverter,
    ) {
        //
    }

    public function handle(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        array $selectColumns,
    ): BulkInsertResult {
        ['sql' => $sql, 'bindings' => $bindings] = $this->generateSql($builder, $selectColumns);

        $rows = $connection->select($sql, $bindings);
        unset($sql, $bindings);

        $connection->commit();

        return new BulkPostgreSqlInsertResult($rows);
    }

    /**
     * @param InsertBuilder $builder
     * @param array $selectColumns
     *
     * @return array{
     *     sql: string,
     *     bindings: mixed[],
     * }
     */
    private function generateSql(InsertBuilder $builder, array $selectColumns): array
    {
        $bindings = [];
        $values = [];

        foreach ($builder->getValues() as $value) {
            $item = [];

            foreach ($builder->getColumns() as $column) {
                $item[] = $this->mixedValueToSqlConverter->handle($value[$column] ?? null, $bindings);
            }
            $values[] = implode(',', $item);
        }

        return [
            'sql' => sprintf(
                'insert into %s (%s) values (%s) %s returning %s',
                $builder->getInto(),
                implode(',', $builder->getColumns()),
                implode('),(', $values),
                $builder->doNothingAtConflict() ? 'on conflict do nothing' : '',
                implode(',', $selectColumns)
            ),
            'bindings' => $bindings,
        ];
    }
}
