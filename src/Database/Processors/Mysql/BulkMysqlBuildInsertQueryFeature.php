<?php

namespace Lapaliv\BulkUpsert\Database\Processors\Mysql;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Features\BulkConvertValueToSqlFeature;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderInsert;

class BulkMysqlBuildInsertQueryFeature
{
    public function __construct(
        private BulkConvertValueToSqlFeature $convertValueToSqlFeature
    )
    {
        //
    }

    public function handle(BulkSqlBuilderInsert $builder): array
    {
        $bindings = [];
        $sql = ['INSERT'];

        if ($builder->getIgnore()) {
            $sql[] = 'IGNORE';
        }

        $values = [];
        foreach ($builder->getValues() as $row) {
            $item = [];
            foreach ($builder->getFields() as $field) {
                $item[] = $this->convertValueToSqlFeature->handle($row[$field] ?? null, $bindings);
            }

            $values = implode(',', $item);
        }

        $sql[] = sprintf(
            'INTO %s (%s) VALUES (%s)',
            $builder->getTable(),
            implode(',', $builder->getFields()),
            implode('),(', $values)
        );

        if ($builder->getPrimaryKeyName() !== null) {
            $sql[] = sprintf(
                '; SELECT LAST_INSERT_ID() as %s',
                $builder->getPrimaryKeyName()
            );
        }

        return [
            'sql' => implode(' ', $sql),
            'bindings' => $bindings,
        ];
    }
}
