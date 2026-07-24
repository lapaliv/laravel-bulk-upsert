<?php

namespace Lapaliv\BulkUpsert\Grammars;

use Illuminate\Database\Query\Grammars\Grammar;
use LogicException;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderCase;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCallback;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCondition;
use Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereIn;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\SelectBulkBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkGrammar;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;

/**
 * The DBMS-agnostic part of the SQL generation.
 *
 * The three dialects differ only in:
 * - the "insert ... ignore" syntax ({@see \Lapaliv\BulkUpsert\Grammars\BaseBulkGrammar::compileInsertIgnoreInfix()},
 *   {@see \Lapaliv\BulkUpsert\Grammars\BaseBulkGrammar::compileInsertSuffix()});
 * - the conditional expression of a single-branch CASE
 *   ({@see \Lapaliv\BulkUpsert\Grammars\BaseBulkGrammar::compileConditionalSet()});
 * - the support of LIMIT in UPDATE/DELETE ({@see \Lapaliv\BulkUpsert\Grammars\BaseBulkGrammar::supportsLimit()}).
 *
 * Identifiers are quoted with the Laravel grammar of the corresponding dialect,
 * so that reserved words used as column names do not break the statements.
 *
 * @internal
 */
abstract class BaseBulkGrammar implements BulkGrammar
{
    /**
     * The values which have to be bound to the compiled statement.
     *
     * @var array
     */
    private array $bindings = [];

    /**
     * Create a new grammar instance.
     *
     * @param \Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter $mixedValueToSqlConverter
     * @param \Illuminate\Database\Query\Grammars\Grammar $laravelGrammar The Laravel grammar of the same dialect.
     */
    public function __construct(
        private readonly MixedValueToSqlConverter $mixedValueToSqlConverter,
        private readonly Grammar $laravelGrammar,
    )
    {
        //
    }

    /**
     * Compile the insert statement into SQL.
     *
     * The columns of the builder define the order of the values. A row which does not
     * have one of the columns gets a null value in its place.
     *
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return string
     */
    public function insert(InsertBuilder $builder): string
    {
        $values = [];

        foreach ($builder->getValues() as $value) {
            $item = [];

            foreach ($builder->getColumns() as $column) {
                $item[] = $this->toSql($value[$column] ?? null);
            }
            $values[] = implode(',', $item);
        }

        $columns = array_map(
            fn(string $column) => $this->wrap($column),
            $builder->getColumns()
        );

        return trim(
            sprintf(
                'insert%s into %s (%s) values (%s)%s',
                $this->compileInsertIgnoreInfix($builder),
                $this->wrapTable($builder->getTable() ?? throw new LogicException('The table of the insert builder is not set.')),
                implode(',', $columns),
                implode('),(', $values),
                $this->compileInsertSuffix($builder),
            )
        );
    }

    /**
     * Compile the update statement into SQL.
     *
     * A set which keeps a CASE with one branch only is compiled by the conditional
     * expression of the dialect. A set with several branches becomes a full CASE
     * expression, and a plain value is compiled as it is. The LIMIT part is only
     * added when the dialect supports it.
     *
     * @param \Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder $builder
     *
     * @return string
     */
    public function update(UpdateBulkBuilder $builder): string
    {
        $sets = [];

        foreach ($builder->getSets() as $field => $set) {
            $whens = [];

            if ($set instanceof BuilderCase && count($set->getWhens()) === 1) {
                $when = $set->getWhens()[0];
                $sets[] = sprintf(
                    '%s = %s',
                    $this->wrap($field),
                    $this->compileConditionalSet(
                        $this->getSqlWhereClause($when->getWheres()),
                        $this->compileCaseOperand($when->getThen()),
                        $this->compileCaseOperand($set->getElse()),
                    ),
                );
            } elseif ($set instanceof BuilderCase) {
                foreach ($set->getWhens() as $when) {
                    $whens[] = sprintf(
                        'when %s then %s',
                        $this->getSqlWhereClause($when->getWheres()),
                        $this->compileCaseOperand($when->getThen())
                    );
                }

                $sets[] = sprintf(
                    '%s = case %s else %s end',
                    $this->wrap($field),
                    implode(' ', $whens),
                    $this->compileCaseOperand($set->getElse()),
                );
            } else {
                $sets[] = sprintf(
                    '%s = %s',
                    $this->wrap($field),
                    $this->toSql($set),
                );
            }
        }

        $sql = sprintf(
            'update %s set %s where %s',
            $this->wrapTable($builder->getTable() ?? throw new LogicException('The table of the update builder is not set.')),
            implode(',', $sets),
            $this->getSqlWhereClause($builder->getWheres())
        );

        $limit = $builder->getLimit();

        if ($this->supportsLimit() && $limit !== null) {
            $sql .= sprintf(' limit %d', $limit);
        }

        return $sql;
    }

    /**
     * Compile the delete statement into SQL.
     *
     * The LIMIT part is only added when the dialect supports it.
     *
     * @param \Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder $builder
     *
     * @return string
     */
    public function delete(DeleteBulkBuilder $builder): string
    {
        $sql = sprintf(
            'delete from %s where %s',
            $this->wrapTable($builder->getTable()),
            $this->getSqlWhereClause($builder->getWheres())
        );

        if ($this->supportsLimit()) {
            $sql .= sprintf(' limit %d', $builder->getLimit());
        }

        return $sql;
    }

    /**
     * Get the values which have to be bound to the compiled statement.
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get the keyword which is placed between `insert` and `into`.
     *
     * The keyword mutes the unique conflicts (e.g. ` ignore` in MySQL). The result is
     * an empty string when the dialect does not have such a keyword.
     *
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return string
     */
    abstract protected function compileInsertIgnoreInfix(InsertBuilder $builder): string;

    /**
     * Get the tail of the insert statement.
     *
     * The tail keeps the parts like ` on conflict do nothing` and ` returning ...`
     * in PostgreSQL. The result is an empty string when the dialect does not have them.
     *
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return string
     */
    abstract protected function compileInsertSuffix(InsertBuilder $builder): string;

    /**
     * Compile a CASE with a single WHEN branch into the expression of the dialect.
     *
     * For example, it is `if($condition, $then, $else)` in MySQL.
     *
     * @param string $condition
     * @param string $then
     * @param string $else
     *
     * @return string
     */
    abstract protected function compileConditionalSet(string $condition, string $then, string $else): string;

    /**
     * Determine whether the dialect supports LIMIT in update and delete statements.
     *
     * @return bool
     */
    abstract protected function supportsLimit(): bool;

    /**
     * Quote the given identifier with the Laravel grammar of the dialect.
     *
     * @param string $identifier
     *
     * @return string
     */
    protected function wrap(string $identifier): string
    {
        return $this->laravelGrammar->wrap($identifier);
    }

    /**
     * Quote the given table name with the Laravel grammar of the dialect.
     *
     * @param string $table
     *
     * @return string
     */
    protected function wrapTable(string $table): string
    {
        return $this->laravelGrammar->wrapTable($table);
    }

    /**
     * Convert the given value to its SQL representation.
     *
     * The value can be added to the bindings of the grammar by the converter.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function toSql(mixed $value): string
    {
        return $this->mixedValueToSqlConverter->handle($this->laravelGrammar, $value, $this->bindings);
    }

    /**
     * Compile a THEN or an ELSE operand of a CASE expression.
     *
     * A raw expression here is always a reference to the column itself
     * ({@see \Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder::addSet()}), so it is quoted as an identifier.
     *
     * @param \Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression|bool|float|int|string|null $operand
     *
     * @return string
     */
    private function compileCaseOperand(BuilderRawExpression|int|float|string|bool|null $operand): string
    {
        if ($operand instanceof BuilderRawExpression) {
            return $this->wrap($operand->get());
        }

        return $this->toSql($operand);
    }

    /**
     * Compile the given conditions into the body of a WHERE clause.
     *
     * A nested group is compiled by the same method and is wrapped in the brackets.
     * A condition with a null value and an equality operator becomes `is null` or
     * `is not null`. An IN condition with one value only becomes an equality, and a
     * null inside its list gets its own `is null` branch.
     *
     * @param \Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCallback[]|\Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereCondition[]|\Lapaliv\BulkUpsert\Builders\Clauses\Where\BuilderWhereIn[] $wheres
     *
     * @return string
     */
    private function getSqlWhereClause(array $wheres): string
    {
        $result = [];

        foreach ($wheres as $where) {
            if (!empty($result)) {
                $result[] = $where->getBoolean();
            }

            if ($where instanceof BuilderWhereCallback) {
                $selectBuilder = new SelectBulkBuilder();
                ($where->getCallback())($selectBuilder);

                $result[] = '(' . $this->getSqlWhereClause($selectBuilder->getWheres()) . ')';
            } elseif ($where instanceof BuilderWhereCondition) {
                $result[] = $where->getValue() === null && $this->isEqualityOperator($where->getOperator())
                    ? sprintf(
                        '%s is %snull',
                        $this->wrap($where->getField()),
                        $where->getOperator() === '=' ? '' : 'not ',
                    )
                    : sprintf(
                        '%s %s %s',
                        $this->wrap($where->getField()),
                        $where->getOperator(),
                        $this->toSql($where->getValue()),
                    );
            } else {
                $values = [];
                $hasNull = false;

                foreach ($where->getValues() as $value) {
                    if ($value === null) {
                        $hasNull = true;

                        continue;
                    }

                    $values[] = $this->toSql($value);
                }

                if (empty($values)) {
                    $result[] = sprintf('%s is null', $this->wrap($where->getField()));
                } else {
                    $condition = count($values) === 1
                        ? sprintf('%s = %s', $this->wrap($where->getField()), $values[0])
                        : sprintf('%s in(%s)', $this->wrap($where->getField()), implode(',', $values));

                    // A `null` inside the list would be dropped by `in (...)`, so it needs its own
                    // `is null` branch to keep matching the rows it was collected from.
                    $result[] = $hasNull
                        ? sprintf('(%s or %s is null)', $condition, $this->wrap($where->getField()))
                        : $condition;
                }
            }
        }

        return implode(' ', $result);
    }

    /**
     * Determine whether the operator has to be written as `is [not] null` for a null value.
     *
     * @param string $operator
     *
     * @return bool
     */
    private function isEqualityOperator(string $operator): bool
    {
        return in_array($operator, ['=', '!=', '<>'], true);
    }
}
