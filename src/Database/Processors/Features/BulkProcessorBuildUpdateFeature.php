<?php

namespace Lapaliv\BulkUpsert\Database\Processors\Features;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderUpdate;
use Lapaliv\BulkUpsert\Features\BulkCollapseArrayFeature;

class BulkProcessorBuildUpdateFeature
{
    public function __construct(
        private BulkCollapseArrayFeature $collapseArrayFeature,
        private BulkProcessorBuildCaseClauseFeature $buildCaseClauseFeature,
        private BulkProcessorBuildWhereClauseFeature $whereClauseFeature,
    )
    {
        //
    }

    public function handle(BulkSqlBuilderUpdate $builder): array
    {
        $bindings = [];
        $result = 'UPDATE ';

        $result .= $builder->getTable() . ' SET ';
        $setParts = [];

        foreach ($builder->getSets() as $field => $set) {
            $builtSet = $this->buildCaseClauseFeature->handle($set);
            $setParts[] = sprintf(
                '%s = %s',
                $field,
                $builtSet['sql'],
            );

            $bindings[] = $builtSet['bindings'];
        }

        $result .= implode(',', $setParts);

        $whereClause = $this->whereClauseFeature->handle($builder->where());

        if (empty($whereClause['sql']) === false) {
            $result .= ' WHERE' . $whereClause['sql'];
            $bindings[] = $whereClause['bindings'];
        }

        if (isset($this->limit)) {
            $result .= ' LIMIT ' . $this->limit;
        }

        return [
            'sql' => $result,
            'bindings' => $this->collapseArrayFeature->handle($bindings),
        ];
    }
}
