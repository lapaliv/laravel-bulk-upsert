<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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

    public function handle(
        Model $eloquent,
        Collection $collection,
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
