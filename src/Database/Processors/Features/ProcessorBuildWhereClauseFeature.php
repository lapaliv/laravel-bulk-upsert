<?php

namespace Lapaliv\BulkUpsert\Database\Processors\Features;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\BulkSqlBuilderWhereClause;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\WhereClause\BulkSqlBuilderWhereClauseCallback;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\WhereClause\BulkSqlBuilderWhereClauseCondition;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\WhereClause\BulkSqlBuilderWhereClauseIn;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Features\BulkConvertValueToSqlFeature;
use Lapaliv\BulkUpsert\Features\BulkCollapseArrayFeature;

class ProcessorBuildWhereClauseFeature
{
    public function __construct(
        private BulkConvertValueToSqlFeature $convertValueToSqlFeature,
        private BulkCollapseArrayFeature $collapseArrayFeature,
    )
    {
        //
    }

    /**
     * @return array{
     *     sql: string,
     *     bindings: mixed[],
     * }
     */
    public function handle(BulkSqlBuilderWhereClause $where): array
    {
        $sql = '';
        $bindings = [];

        foreach ($where->getWheres() as $item) {
            if ($sql !== '') {
                $sql .= ' ' . $item->boolean;
            }

            if ($item instanceof BulkSqlBuilderWhereClauseCallback) {
                $subBuilder = new BulkSqlBuilderWhereClause();
                call_user_func($item->callback, $subBuilder);
                $subBuilderResult = $this->handle($subBuilder);

                $sql .= sprintf('(%s)', $subBuilderResult['sql']);
                $bindings[] = $subBuilderResult['bindings'];
            }

            if ($item instanceof BulkSqlBuilderWhereClauseCondition) {
                $sql .= sprintf(
                    ' %s %s %s',
                    $item->field,
                    $item->operator,
                    $this->convertValueToSqlFeature->handle($item->value, $bindings),
                );
            }

            if ($item instanceof BulkSqlBuilderWhereClauseIn) {
                $values = [];

                foreach ($item->values as $value) {
                    $values[] = $this->convertValueToSqlFeature->handle($value, $bindings);
                }

                $sql .= sprintf(
                    ' %s IN(%s)',
                    $item->field,
                    implode(',', $values),
                );
            }
        }

        return [
            'sql' => $sql,
            'bindings' => $this->collapseArrayFeature->handle($bindings),
        ];
    }
}
