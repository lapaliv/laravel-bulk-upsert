<?php

namespace Lapaliv\BulkUpsert\Drivers\MySql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCallback;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCondition;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereIn;
use Lapaliv\BulkUpsert\Builders\SelectBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Throwable;

class MySqlDriverUpdate
{
    public function __construct(private MixedValueToSqlConverter $mixedValueToSqlConverter)
    {
        //
    }

    public function handle(
        ConnectionInterface $connection,
        UpdateBuilder $builder,
    ): int {
        ['sql' => $sql, 'bindings' => $bindings] = $this->generateSql($builder);

        $connection->beginTransaction();

        try {
            $result = $connection->update($sql, $bindings);
            $connection->commit();
            return $result;
        } catch (Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    private function generateSql(UpdateBuilder $builder): array
    {
        $bindings = [];
        $sets = [];

        foreach ($builder->getSets() as $field => $set) {
            $whens = [];

            foreach ($set->getWhens() as $when) {
                $whens[] = sprintf(
                    'when %s then %s',
                    $this->getSqlWhereClause($when->getWheres(), $bindings),
                    $when->getThen()
                );
            }

            $sets[] = sprintf(
                '%s = case %s else %s end',
                $field,
                implode(' ', $whens),
                $this->mixedValueToSqlConverter->handle($set->getElse(), $bindings),
            );
        }

        $sql = sprintf(
            'update %s set %s where %s',
            $builder->getTable(),
            implode(',', $sets),
            $this->getSqlWhereClause($builder->getWheres(), $bindings)
        );

        if ($builder->getLimit() !== null) {
            $sql .= sprintf(' limit %d', $builder->getLimit());
        }

        return compact('sql', 'bindings');
    }

    private function getSqlWhereClause(array $wheres, array &$bindings): string
    {
        $result = [];

        foreach ($wheres as $where) {
            if (empty($result) === false) {
                $result[] = $where->boolean;
            }

            if ($where instanceof BuilderWhereCallback) {
                $selectBuilder = new SelectBuilder();
                call_user_func($where->callback, $selectBuilder);

                $result[] = '(' . $this->getSqlWhereClause($selectBuilder->getWheres(), $bindings) . ')';
            } elseif ($where instanceof BuilderWhereCondition) {
                $result[] = sprintf(
                    '%s %s %s',
                    $where->field,
                    $where->operator,
                    $this->mixedValueToSqlConverter->handle($where->value, $bindings),
                );
            } elseif ($where instanceof BuilderWhereIn) {
                $values = [];
                foreach ($where->values as $value) {
                    $values[] = $this->mixedValueToSqlConverter->handle($value, $bindings);
                }

                $result[] = sprintf(
                    '%s IN(%s)',
                    $where->field,
                    implode(',', $values),
                );
            }
        }

        return implode(' ', $result);
    }
}