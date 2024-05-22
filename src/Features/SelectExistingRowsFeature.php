<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @internal
 */
class SelectExistingRowsFeature
{
    public function __construct(
        private AddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
    ) {
        //
    }

    /**
     * @param Model $eloquent
     * @param SupportCollection $collection
     * @param array $uniqueBy
     * @param array $selectColumns
     * @param string|null $deletedAtColumn
     * @param bool $withTrashed
     *
     * @return Collection
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
    ): Collection {
        $builder = $eloquent->newQuery()
            ->select($selectColumns)
            ->limit($collection->count());

        if ($withTrashed && $deletedAtColumn !== null) {
            call_user_func([$builder, 'withTrashed']);
        }

        $this->addWhereClauseToBuilderFeature->handle($builder, $uniqueBy, $collection);

        return $builder->get();
    }
}
