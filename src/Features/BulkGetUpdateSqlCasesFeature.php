<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Features\BulkConvertValueToSqlFeature;

class BulkGetUpdateSqlCasesFeature
{
    private array $cases = [];
    private array $bindings = [];

    public function __construct(
        private array $rows,
        private array $uniqueAttributes,
        private bool $allRowsHavePrimary,
        private string $primaryKeyName,
        private BulkConvertValueToSqlFeature $convertValueToSqlFeature,
    )
    {
        //
    }

    /**
     * @return array{
     *     cases: array<string, string>,
     *     bindings: array<string, mixed[]>,
     * }
     */
    public function handle(): array
    {
        foreach ($this->rows as $row) {
            [
                'condition' => $whenCondition,
                'bindings' => $whenBindings,
            ] = $this->getCondition($row);

            $this->addRowCases(
                $row,
                $whenCondition,
                $whenBindings
            );
        }

        return [
            'cases' => $this->collectCasePartsToCases(),
            'bindings' => $this->bindings,
        ];
    }

    /**
     * @return array{
     *     when: string,
     *     bindings: mixed[]
     * }
     */
    private function getCondition(array $row): array
    {
        $condition = '';
        $bindings = [];

        if ($this->primaryKeyName !== null
            && array_key_exists($this->primaryKeyName, $row)
        ) {
            if ($this->allRowsHavePrimary === false) {
                $condition .= sprintf('%s = ', $this->primaryKeyName);
            }

            $condition .= sprintf(
                '%s',
                $this->convertValueToSqlFeature->handle(
                    $row[$this->primaryKeyName],
                    $bindings
                )
            );
        } else {
            $whenParts = [];
            foreach ($this->uniqueAttributes as $attribute) {
                $whenParts[] = sprintf(
                    '%s = %s',
                    $attribute,
                    $this->convertValueToSqlFeature->handle(
                        $row[$attribute] ?? null,
                        $bindings
                    )
                );
            }

            $condition .= implode(' AND ', $whenParts);
        }

        return compact('condition', 'bindings');
    }

    private function addRowCases(
        array $row,
        string $whenCondition,
        array $whenBindings,
    ): void
    {
        foreach ($row as $key => $value) {
            if ($this->primaryKeyName === $key) {
                continue;
            }

            if (in_array($key, $this->uniqueAttributes, true)) {
                continue;
            }

            $this->cases[$key] ??= [];
            $this->bindings[$key] ??= [];

            foreach ($whenBindings as $binding) {
                $this->binding[$key][] = $binding;
            }

            $this->cases[$key] = sprintf(
                'WHEN %s THEN %s',
                $whenCondition,
                $this->convertValueToSqlFeature->handle(
                    $value,
                    $this->bindings[$key]
                )
            );
        }
    }

    private function collectCasePartsToCases(): array
    {
        $result = [];

        foreach ($this->cases as $key => $payloads) {
            $result[$key] = sprintf(
                'CASE %s %s ELSE %s END',
                $this->allRowsHavePrimary ? $this->primaryKeyName : '',
                implode(' ', $payloads),
                $key
            );
        }

        return $result;
    }
}
