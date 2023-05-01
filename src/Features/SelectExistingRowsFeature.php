<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
    ): Collection {
        $builder = $eloquent->newQuery()
            ->select($selectColumns)
            ->limit($collection->count());

        if ($deletedAtColumn !== null) {
            /** @phpstan-ignore-next-line */
            $builder->withTrashed();
        }

        $this->addWhereClauseToBuilderFeature->handle($builder, $uniqueBy, $collection);

        $result = $builder->get();

        if ($result instanceof Collection) {
            return $result;
        }

        return new Collection($result->all());
    }
}
