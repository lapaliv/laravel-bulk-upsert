<?php

namespace Lapaliv\BulkUpsert\Features;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * @internal
 */
class GetDeleteBuilderFeature
{
    public function __construct(
        private AddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
    ) {
        //
    }

    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        array $dateFields,
        ?string $deletedAtColumn,
        bool $force,
        DateTimeInterface $deletedAt,
    ): UpdateBulkBuilder|DeleteBulkBuilder|null {
        if ($deletedAtColumn === null || $force) {
            return $this->getDeleteBuilder($eloquent, $data);
        }

        return $this->getUpdateBuilder($eloquent, $data, $dateFields, $deletedAtColumn, $deletedAt);
    }

    private function getDeleteBuilder(
        Model $eloquent,
        BulkAccumulationEntity $data
    ): ?DeleteBulkBuilder {
        $models = $data->getNotSkippedModels('skipDeleting');

        if ($models->isEmpty()) {
            return null;
        }

        $result = new DeleteBulkBuilder();
        $result->from($eloquent->getTable());
        $this->addWhereClauseToBuilderFeature->handle($result, $data->uniqueBy, $models);

        return $result->limit($models->count());
    }

    private function getUpdateBuilder(
        Model $eloquent,
        BulkAccumulationEntity $data,
        array $dateFields,
        string $deletedAtColumn,
        DateTimeInterface $deletedAt,
    ): ?UpdateBulkBuilder {
        $models = $data->getNotSkippedModels('skipDeleting');
        $result = new UpdateBulkBuilder();
        $result->table($eloquent->getTable());

        if ($models->isEmpty()) {
            return null;
        }

        $result->addSimpleSet(
            $deletedAtColumn,
            $deletedAt->format($dateFields[$deletedAtColumn] ?? 'Y-m-d H:i:s')
        );

        $this->addWhereClauseToBuilderFeature->handle($result, $data->uniqueBy, $models);

        return $result->limit($models->count());
    }
}
