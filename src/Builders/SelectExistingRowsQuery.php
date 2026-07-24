<?php

namespace Lapaliv\BulkUpsert\Builders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Selects the rows matching the given models by their unique attributes.
 *
 * @internal
 */
readonly class SelectExistingRowsQuery
{
    /**
     * Create a new query instance.
     *
     * @param \Lapaliv\BulkUpsert\Builders\WhereClauseApplier $whereClauseApplier
     */
    public function __construct(
        private WhereClauseApplier $whereClauseApplier,
    )
    {
        //
    }

    /**
     * Select the rows which match the given models by their unique attributes.
     *
     * The number of the selected rows is limited by the size of the collection.
     * The soft-deleted rows are only selected when the trashed rows are asked for.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Illuminate\Support\Collection $collection The models which the rows are searched for.
     * @param string[] $uniqueBy The columns which the models are matched by.
     * @param array $selectColumns The columns which have to be selected.
     * @param string|null $deletedAtColumn
     * @param bool $withTrashed
     *
     * @return \Illuminate\Database\Eloquent\Collection
     *
     * @psalm-return Collection<array-key, Model>
     */
    public function handle(
        Model $eloquent,
        SupportCollection $collection,
        array $uniqueBy,
        array $selectColumns,
        ?string $deletedAtColumn = null,
        bool $withTrashed = false,
    ): Collection
    {
        $builder = $eloquent->newQuery()
            ->select($selectColumns)
            ->limit($collection->count());

        if ($withTrashed && $deletedAtColumn !== null) {
            /** @psalm-suppress UndefinedMagicMethod */
            $builder->withTrashed();
        }

        /** @var \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $collection */
        $this->whereClauseApplier->handle($builder, $uniqueBy, $collection);

        /** @var \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model> $rows */
        $rows = $builder->get();

        return $rows;
    }
}
