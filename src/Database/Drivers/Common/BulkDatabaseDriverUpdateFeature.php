<?php

namespace Lapaliv\BulkUpsert\Database\Drivers\Common;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Contracts\BulkDatabaseProcessor;
use Lapaliv\BulkUpsert\Database\BulkSqlBuilder;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\BulkSqlBuilderCaseClause;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderUpdate;
use Lapaliv\BulkUpsert\Features\BulkAddWhereClauseToBuilderFeature;
use Throwable;

class BulkDatabaseDriverUpdateFeature
{
    public function __construct(
        private BulkSqlBuilder $sqlBuilder,
        private BulkAddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
    )
    {
        //
    }

    public function handle(
        BulkDatabaseProcessor $processor,
        ConnectionInterface $connection,
        string $table,
        array $uniqueAttributes,
        array $rows,
    ): int
    {
        $sqlBuilder = $this->getSqlBuilder($table, $rows, $uniqueAttributes);
        [
            'sql' => $sql,
            'bindings' => $bindings,
        ] = $processor->update($sqlBuilder);

        $connection->beginTransaction();

        try {
            $result = $connection->update($sql, $bindings);

            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    private function getSqlBuilder(
        string $table,
        array $rows,
        array $uniqueAttributes,
    ): BulkSqlBuilderUpdate
    {
        $sqlBuilder = $this->sqlBuilder
            ->update()
            ->table($table)
            ->limit(count($rows));

        // set clause
        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                if (in_array($key, $uniqueAttributes, true)) {
                    continue;
                }

                $set = $sqlBuilder->getSet($key);

                if ($set === null) {
                    $set = new BulkSqlBuilderCaseClause();
                    $sqlBuilder->set($key, $set);
                }

                $sqlCaseBody = $set->elseField($key)
                    ->newBody()
                    ->then($value);

                foreach ($uniqueAttributes as $uniqueAttribute) {
                    $sqlCaseBody->where($uniqueAttribute, '=', $row[$uniqueAttribute]);
                }
            }
        }

        // where clause
        $this->addWhereClauseToBuilderFeature->handle(
            $sqlBuilder->where(),
            $uniqueAttributes,
            $rows,
        );

        return $sqlBuilder;
    }
}
