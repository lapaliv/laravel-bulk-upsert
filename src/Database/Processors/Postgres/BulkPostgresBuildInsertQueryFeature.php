<?php

namespace Lapaliv\BulkUpsert\Database\Processors\Postgres;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Features\BulkConvertValueToSqlFeature;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderInsert;

class BulkPostgresBuildInsertQueryFeature
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

        $values = [];
        foreach ($builder->getValues() as $row) {
            $item = [];
            foreach ($row as $value) {
                $item[] = $this->convertValueToSqlFeature->handle($value, $bindings);
            }

            $values = implode(',', $item);
        }

        $sql[] = sprintf(
            'INTO %s (%s) VALUES (%s)',
            $builder->getTable(),
            implode(',', $builder->getFields()),
            implode('),(', $values)
        );

        if ($builder->getIgnore()) {
            $sql[] = 'ON CONFLICT DO NOTHING';
        }

        if ($builder->getPrimaryKeyName() !== null) {
            $sql[] = sprintf(
                'RETURNING %s',
                $builder->getPrimaryKeyName()
            );
        }

        return [
            'sql' => implode(' ', $sql),
            'bindings' => $bindings,
        ];
    }
}
