<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * Gives a query builder the bulk methods of the package.
 *
 * @template TCollection of \Illuminate\Database\Eloquent\Collection
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
trait BulkBuilderTrait
{
    /**
     * Create a new bulk instance for the model of the builder.
     *
     * @return \Lapaliv\BulkUpsert\Bulk<TCollection, TModel>
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function bulk(): Bulk
    {
        return new Bulk($this->getModel());
    }

    /**
     * Select the rows chunk by chunk, fill in the values and update these rows.
     *
     * Every row is loaded and the values are set through `setAttribute()`, so the casts and
     * the mutators run exactly as for `Model::save()`, with or without listeners.
     *
     * The rows are paginated by the primary key, so a row which the update moves out of the
     * WHERE clause cannot shift the next chunks, and only one chunk is kept in memory. When
     * the builder has its own order, limit or offset, the keys are taken once with them and
     * the rows are loaded by these keys chunk by chunk. The given builder is never changed.
     *
     * @param array<string, mixed> $values
     * @param string|string[]|null $unique
     * @param int $chunk
     *
     * @return int The number of the selected rows.
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     * @throws \InvalidArgumentException When the chunk size is not positive.
     * @throws \LogicException When the builder has GROUP BY, HAVING or UNION, or is a DISTINCT
     *     query whose columns do not contain the primary key.
     *
     * @psalm-api
     */
    public function selectAndUpdateMany(
        array $values,
        string|array|null $unique = null,
        int $chunk = 100
    ): int
    {
        if ($chunk < 1) {
            throw new InvalidArgumentException('The chunk size must be positive.');
        }

        $model = $this->getModel();
        $keyName = $model->getKeyName();
        $bulk = $this->bulk()
            ->chunk($chunk)
            ->uniqueBy((array)($unique ?? [$keyName]));

        // The global scopes are applied once, here, and are not applied again by the queries
        // below: a scope with `orWhere()` must end up inside the wrapped conditions, and a
        // scope with a limit or an offset must not cut the chunks again.
        $prepared = $this->prepareForUpdate();
        $base = $prepared->getQuery();

        if (($base->groups ?? []) !== [] || ($base->havings ?? []) !== [] || ($base->unions ?? []) !== []) {
            // The rows are loaded again by their keys, which cannot keep the meaning of a
            // grouped or united query; failing before any write is safer than writing other rows.
            throw new LogicException('selectAndUpdateMany() does not support GROUP BY, HAVING and UNION.');
        }

        // The columns are qualified by the alias of the FROM clause, if there is one.
        $qualifier = $this->tableQualifier($base, $model);
        $qualifiedKey = $qualifier . '.' . $keyName;
        $result = 0;

        if ($base->distinct !== false && $base->columns !== null && !$this->selectsKey($base->columns, $model, $qualifier)) {
            // The rows are loaded by their keys, and adding the key would change what is
            // distinct, so such a query is rejected.
            throw new LogicException(
                'selectAndUpdateMany() needs the primary key in the columns of a DISTINCT query.'
            );
        }

        // A DISTINCT query goes to the second branch, which keeps its own projection:
        // widening it to every column would change what is distinct (and PostgreSQL cannot
        // compare `json` columns).
        if (($base->orders ?? []) === [] && $base->limit === null && $base->offset === null && $base->distinct === false) {
            // Keyset pagination by the primary key: only one chunk is in memory, and the key is
            // never written, so a row which leaves the WHERE clause cannot shift the next chunk.
            $lastKey = null;

            while (true) {
                $builder = $this->cloneForUpdate($prepared, $qualifier)->reorder($qualifiedKey)->limit($chunk);

                if ($lastKey !== null) {
                    $builder->where($qualifiedKey, '>', $lastKey);
                }

                /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $collection */
                $collection = $builder->get();

                if ($collection->isEmpty()) {
                    break;
                }

                // The cursor is taken before the values are set and the listeners run.
                /** @psalm-suppress MixedAssignment */
                $lastKey = $collection->last()?->getKey();
                $result += $this->updateSelected($bulk, $collection, $values);

                if ($collection->count() < $chunk || $lastKey === null) {
                    break;
                }
            }

            return $result;
        }

        // The builder has its own order, limit or offset, which cannot be combined with the
        // keyset. The keys are taken once with them, and the rows are loaded by these keys
        // chunk by chunk. All the keys (only the keys) are kept in memory then; only a limit
        // bounds their number. The rows are not checked against the WHERE clause again.
        // The key is taken under its own alias, so a JOIN with another `id` cannot make it
        // ambiguous. The own projection of the builder is kept with its bindings: an ORDER BY
        // may refer to a computed column, and a DISTINCT query must stay as distinct as it was
        // (its columns contain the key, checked above, so the alias changes nothing there).
        $keysBuilder = clone $prepared;
        // A random alias cannot collide with a column or an alias of the builder.
        $keyAlias = 'bulk_upsert_key_' . bin2hex(random_bytes(6));

        if ($base->columns === null) {
            if ($base->distinct === false) {
                $keysBuilder->select($qualifiedKey . ' as ' . $keyAlias);
            } else {
                $keysBuilder->select([$qualifier . '.*', $qualifiedKey . ' as ' . $keyAlias]);
            }
        } else {
            $keysBuilder->addSelect($qualifiedKey . ' as ' . $keyAlias);
        }

        // The rows are streamed and only their keys are kept, so no collection of the whole
        // projection is built in PHP (the PDO driver may still buffer the result set).
        /** @var list<int|string> $keys */
        $keys = [];

        foreach ($keysBuilder->toBase()->cursor() as $keyRow) {
            /** @var int|string $key */
            $key = ((array)$keyRow)[$keyAlias];
            $keys[] = $key;
        }

        foreach (array_chunk($keys, $chunk) as $chunkKeys) {
            // The keys have already passed the WHERE clause and every scope of the builder, so
            // the rows are loaded by the keys alone: a scope with its own limit or offset would
            // otherwise cut the chunk again.
            $builder = $model->newQueryWithoutScopes()
                ->useWritePdo()
                ->setEagerLoads($this->getEagerLoads())
                ->select($model->qualifyColumn('*'));

            /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $loaded */
            $loaded = $builder->whereKey($chunkKeys)->get();
            // The rows are handled in the order of the builder, which the keys keep.
            $positions = array_flip(array_map(static fn (int|string $key): string => (string)$key, $chunkKeys));
            $collection = $loaded->sortBy(
                fn (Model $row): int => $positions[(string)$row->getKey()] ?? PHP_INT_MAX
            )->values();

            $result += $this->updateSelected($bulk, $collection, $values);
        }

        return $result;
    }

    /**
     * Get the name which qualifies the columns of the model in the query.
     *
     * It is the alias of `from('table as alias')`, or the table of the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return string
     */
    private function tableQualifier(\Illuminate\Database\Query\Builder $query, Model $model): string
    {
        $from = $query->from;

        if (is_string($from) && preg_match('/^\s*\S+\s+as\s+(\S+)\s*$/i', $from, $matches) === 1) {
            return $matches[1];
        }

        return $model->getTable();
    }

    /**
     * Whether the selected columns contain the primary key of the model.
     *
     * @param array<array-key, mixed> $columns
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $qualifier The table name or the alias of the FROM clause.
     *
     * @return bool
     */
    private function selectsKey(array $columns, Model $model, string $qualifier): bool
    {
        $accepted = [
            '*',
            $model->qualifyColumn('*'),
            $model->getKeyName(),
            $model->getQualifiedKeyName(),
            $qualifier . '.*',
            $qualifier . '.' . $model->getKeyName(),
        ];

        /** @psalm-suppress MixedAssignment A column may be an expression; is_string() checks it. */
        foreach ($columns as $column) {
            if (is_string($column) && in_array($column, $accepted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a clone of the builder with the global scopes applied once and then removed.
     *
     * The rows are read from the writer.
     *
     * @return static
     */
    private function prepareForUpdate(): static
    {
        /** @var static $prepared */
        $prepared = (clone $this)->applyScopes()->withoutGlobalScopes();

        return $prepared->useWritePdo();
    }

    /**
     * Clone the prepared builder for loading the rows which are going to be updated.
     *
     * - Every column of the table is selected: the update needs the primary key, and a
     *   mutator may read any attribute of the row.
     * - All the conditions, also the ones of the global scopes, are wrapped into one group,
     *   so the cursor added after them applies to every condition, also to an `orWhere()`.
     *
     * @param static $prepared
     * @param string $qualifier The table name or the alias of the FROM clause.
     *
     * @return static
     */
    private function cloneForUpdate(self $prepared, string $qualifier): static
    {
        $builder = clone $prepared;
        $builder->select($qualifier . '.*');
        $query = $builder->getQuery();

        if (!empty($query->wheres)) {
            $nested = $query->forNestedWhere();
            $nested->wheres = $query->wheres;
            $nested->bindings['where'] = $query->bindings['where'];
            $query->wheres = [];
            $query->bindings['where'] = [];
            $query->addNestedWhereQuery($nested);
        }

        return $builder;
    }

    /**
     * Set the values on the selected rows through `setAttribute()` and update them.
     *
     * @param \Lapaliv\BulkUpsert\Bulk $bulk
     * @param \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $collection
     * @param array<string, mixed> $values
     *
     * @return int The number of the selected rows.
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    private function updateSelected(Bulk $bulk, \Illuminate\Support\Collection $collection, array $values): int
    {
        foreach ($collection as $row) {
            /** @psalm-suppress MixedAssignment */
            foreach ($values as $attribute => $value) {
                $row->setAttribute($attribute, $value);
            }
        }

        $bulk->update($collection);

        return $collection->count();
    }
}
