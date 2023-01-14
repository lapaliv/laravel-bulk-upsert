<?php

namespace Lapaliv\BulkUpsert\Database\Processors\Features;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\BulkSqlBuilderCaseClause;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\CaseClause\BulkSqlBuilderCaseClauseBody;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Features\BulkConvertValueToSqlFeature;

class BulkProcessorBuildCaseClauseFeature
{
    public function __construct(
        private BulkConvertValueToSqlFeature $convertValueToSqlFeature,
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
    public function handle(BulkSqlBuilderCaseClause $clause): array
    {
        $bindings = [];
        $shouldBeCompact = $clause->isSimple();

        $whenClause = $this->buildWhenClause($clause->getBody(), $shouldBeCompact);
        $bindings = [...$bindings, $whenClause['bindings']];

        if ($shouldBeCompact) {
            $fields = $clause->getWhenUniqueFields();

            $sql = sprintf(
                'CASE %s %s',
                $fields[0],
                $whenClause['sql']
            );
        } else {
            $sql = 'CASE';
            if ($clause->getCondition() !== null) {
                $sql .= ' ' . $clause->getCondition();
            }

            $sql .= ' ' . $whenClause['sql'];
        }

        if ($clause->hasElse()) {
            $sql .= ' ' . $this->convertValueToSqlFeature->handle($clause->getElse(), $bindings);
        } elseif ($clause->hasElseField()) {
            $sql .= ' ELSE ' . $clause->getElseField();
        }

        $sql .= ' END';

        return compact('sql', 'bindings');
    }

    /**
     * @param BulkSqlBuilderCaseClauseBody[] $body
     * @param bool $compact
     * @return array{
     *     sql: string,
     *     bindings: mixed[],
     * }
     */
    private function buildWhenClause(array $body, bool $compact): array
    {
        $sqlParts = [];
        $bindings = [];

        foreach ($body as $when) {
            if ($when->isEmpty()) {
                continue;
            }

            if ($compact) {
                $sql = sprintf(
                    'WHEN %s THEN %s',
                    $this->convertValueToSqlFeature->handle($when->getWheres()[0]['value'], $bindings),
                    $this->convertValueToSqlFeature->handle($when->getThen(), $bindings),
                );
            } else {
                $whenParts = [];

                foreach ($when->getWheres() as $condition) {
                    if (array_key_exists('field', $condition) === false) {
                        $whenParts[] = $this->convertValueToSqlFeature->handle($condition['value'], $bindings);
                    } else {
                        $whenParts[] = sprintf(
                            '%s %s %s',
                            $condition['field'],
                            $condition['operator'],
                            $this->convertValueToSqlFeature->handle($condition['value'], $bindings),
                        );
                    }
                }

                $sql = sprintf(
                    'WHEN %s THEN %s',
                    implode(' AND ', $whenParts),
                    $this->convertValueToSqlFeature->handle($when->getThen(), $bindings),
                );
            }

            $sqlParts[] = $sql;
        }

        return [
            'sql' => implode(' ', $sqlParts),
            'bindings' => [...$bindings],
        ];
    }
}
