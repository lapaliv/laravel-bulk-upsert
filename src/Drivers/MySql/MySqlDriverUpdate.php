<?php

namespace Lapaliv\BulkUpsert\Drivers\MySql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderCase;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCallback;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCondition;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereIn;
use Lapaliv\BulkUpsert\Builders\SelectBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;

/**
 * @internal
 */
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

        return $connection->update($sql, $bindings);
    }

    private function generateSql(UpdateBuilder $builder): array
    {
        $bindings = [];
        $sets = [];

        foreach ($builder->getSets() as $field => $set) {
            $whens = [];

            if ($set instanceof BuilderCase && count($set->getWhens()) === 1) {
                $when = $set->getWhens()[0];
                $sets[] = sprintf(
                    '`%s` = if(%s, %s, `%s`)',
                    $field,
                    $this->getSqlWhereClause($when->getWheres(), $bindings),
                    $this->mixedValueToSqlConverter->handle($when->getThen(), $bindings),
                    $this->mixedValueToSqlConverter->handle($set->getElse(), $bindings),
                );
            } elseif ($set instanceof BuilderCase) {
                foreach ($set->getWhens() as $when) {
                    $whens[] = sprintf(
                        'when %s then %s',
                        $this->getSqlWhereClause($when->getWheres(), $bindings),
                        $this->mixedValueToSqlConverter->handle($when->getThen(), $bindings)
                    );
                }

                $sets[] = sprintf(
                    '`%s` = case %s else `%s` end',
                    $field,
                    implode(' ', $whens),
                    $this->mixedValueToSqlConverter->handle($set->getElse(), $bindings),
                );
            } else {
                $sets[] = sprintf(
                    '`%s` = %s',
                    $field,
                    $this->mixedValueToSqlConverter->handle($set, $bindings),
                );
            }
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
            if (!empty($result)) {
                $result[] = $where->boolean;
            }

            if ($where instanceof BuilderWhereCallback) {
                $selectBuilder = new SelectBuilder();
                call_user_func($where->callback, $selectBuilder);

                $result[] = '(' . $this->getSqlWhereClause($selectBuilder->getWheres(), $bindings) . ')';
            } elseif ($where instanceof BuilderWhereCondition) {
                $result[] = sprintf(
                    '`%s` %s %s',
                    $where->field,
                    $where->operator,
                    $this->mixedValueToSqlConverter->handle($where->value, $bindings),
                );
            } elseif ($where instanceof BuilderWhereIn) {
                $values = [];

                foreach ($where->values as $value) {
                    $values[] = $this->mixedValueToSqlConverter->handle($value, $bindings);
                }

                if (count($values) === 1) {
                    $result[] = sprintf(
                        '`%s` = %s',
                        $where->field,
                        $values[0],
                    );
                } else {
                    $result[] = sprintf(
                        '`%s` in(%s)',
                        $where->field,
                        implode(',', $values),
                    );
                }
            }
        }

        return implode(' ', $result);
    }
}
