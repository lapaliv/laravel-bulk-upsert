<?php

namespace Lapaliv\BulkUpsert\Grammars;

use Lapaliv\BulkUpsert\Builders\Clauses\BuilderCase;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCallback;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCondition;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereIn;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\SelectBulkBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkGrammar;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;

class PostgreSqlGrammar implements BulkGrammar
{
    private array $bindings = [];

    public function __construct(private MixedValueToSqlConverter $mixedValueToSqlConverter)
    {
        //
    }

    public function insert(InsertBuilder $builder): string
    {
        $values = [];

        foreach ($builder->getValues() as $value) {
            $item = [];

            foreach ($builder->getColumns() as $column) {
                $item[] = $this->mixedValueToSqlConverter->handle($value[$column] ?? null, $this->bindings);
            }
            $values[] = implode(',', $item);
        }

        return sprintf(
            'insert into %s (%s) values (%s) %s returning %s',
            $builder->getInto(),
            implode(',', $builder->getColumns()),
            implode('),(', $values),
            $builder->doNothingAtConflict() ? 'on conflict do nothing' : '',
            implode(',', $builder->getSelect())
        );
    }

    public function update(UpdateBulkBuilder $builder): string
    {
        $sets = [];

        foreach ($builder->getSets() as $field => $set) {
            $whens = [];

            if ($set instanceof BuilderCase && count($set->getWhens()) === 1) {
                $when = $set->getWhens()[0];
                $sets[] = sprintf(
                    '%s = case when %s then %s else %s end',
                    $field,
                    $this->getSqlWhereClause($when->getWheres()),
                    $this->mixedValueToSqlConverter->handle($when->getThen(), $this->bindings),
                    $this->mixedValueToSqlConverter->handle($set->getElse(), $this->bindings),
                );
            } elseif ($set instanceof BuilderCase) {
                foreach ($set->getWhens() as $when) {
                    $whens[] = sprintf(
                        'when %s then %s',
                        $this->getSqlWhereClause($when->getWheres()),
                        $this->mixedValueToSqlConverter->handle($when->getThen(), $this->bindings)
                    );
                }

                $sets[] = sprintf(
                    '%s = case %s else %s end',
                    $field,
                    implode(' ', $whens),
                    $this->mixedValueToSqlConverter->handle($set->getElse(), $this->bindings),
                );
            } else {
                $sets[] = sprintf(
                    '%s = %s',
                    $field,
                    $this->mixedValueToSqlConverter->handle($set, $this->bindings),
                );
            }
        }

        return sprintf(
            'update %s set %s where %s',
            $builder->getTable(),
            implode(',', $sets),
            $this->getSqlWhereClause($builder->getWheres())
        );
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    private function getSqlWhereClause(array $wheres): string
    {
        $result = [];

        foreach ($wheres as $where) {
            if (!empty($result)) {
                $result[] = $where->boolean;
            }

            if ($where instanceof BuilderWhereCallback) {
                $selectBuilder = new SelectBulkBuilder();
                call_user_func($where->callback, $selectBuilder);

                $result[] = '(' . $this->getSqlWhereClause($selectBuilder->getWheres()) . ')';
            } elseif ($where instanceof BuilderWhereCondition) {
                $result[] = sprintf(
                    '%s %s %s',
                    $where->field,
                    $where->operator,
                    $this->mixedValueToSqlConverter->handle($where->value, $this->bindings),
                );
            } elseif ($where instanceof BuilderWhereIn) {
                $values = [];

                foreach ($where->values as $value) {
                    $values[] = $this->mixedValueToSqlConverter->handle($value, $this->bindings);
                }

                if (count($values) === 1) {
                    $result[] = sprintf(
                        '%s = %s',
                        $where->field,
                        $values[0],
                    );
                } else {
                    $result[] = sprintf(
                        '%s in(%s)',
                        $where->field,
                        implode(',', $values),
                    );
                }
            }
        }

        return implode(' ', $result);
    }
}
