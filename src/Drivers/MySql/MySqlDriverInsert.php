<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */
/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Drivers\MySql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Throwable;

class MySqlDriverInsert
{
    public function __construct(
        private MixedValueToSqlConverter $mixedValueToSqlConverter,
    ) {
        //
    }

    public function handle(ConnectionInterface $connection, InsertBuilder $builder, ?string $primaryKeyName): ?int
    {
        ['sql' => $sql, 'bindings' => $bindings] = $this->generateSql($builder);

        $connection->beginTransaction();
        $lastPrimaryBeforeInserting = null;

        try {
            if ($primaryKeyName !== null) {
                $lastRow = $connection->selectOne(
                    sprintf(
                        'select max(%s) as id from %s',
                        $primaryKeyName,
                        $builder->getInto()
                    )
                );

                $lastPrimaryBeforeInserting = $lastRow->id;
            }

            $connection->insert($sql, $bindings);
            $connection->commit();

            return is_numeric($lastPrimaryBeforeInserting) || is_int($lastPrimaryBeforeInserting)
                ? (int)$lastPrimaryBeforeInserting
                : null;
        } catch (Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * @param InsertBuilder $builder
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
                'insert %s into %s (`%s`) values (%s)',
                $builder->doNothingAtConflict() ? 'ignore' : '',
                $builder->getInto(),
                implode('`,`', $builder->getColumns()),
                implode('),(', $values),
            ),
            'bindings' => $bindings,
        ];
    }
}
