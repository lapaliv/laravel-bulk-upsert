<?php

namespace Lapaliv\BulkUpsert\Drivers\PostgreSql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;

class PostgreSqlDriverQuietInsert
{
    public function __construct(
        private MixedValueToSqlConverter $mixedValueToSqlConverter,
    ) {
        //
    }

    public function handle(ConnectionInterface $connection, InsertBuilder $builder): void
    {
        ['sql' => $sql, 'bindings' => $bindings] = $this->generateSql($builder);

        $connection->insert($sql, $bindings);
        unset($sql, $bindings);
    }

    /**
     * @param InsertBuilder $builder
     *
     * @return array{
     *     sql: string,
     *     bindings: mixed[],
     * }
     */
    private function generateSql(InsertBuilder $builder): array
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
                'insert into %s (%s) values (%s) %s',
                $builder->getInto(),
                implode(',', $builder->getColumns()),
                implode('),(', $values),
                $builder->doNothingAtConflict() ? 'on conflict do nothing' : '',
            ),
            'bindings' => $bindings,
        ];
    }
}
