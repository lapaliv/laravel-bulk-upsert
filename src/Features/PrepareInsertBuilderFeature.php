<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Support\BulkCallback;

class PrepareInsertBuilderFeature
{
    public function __construct(
        private FireModelEventsFeature $fireModelEventsFeature,
        private AttributesToScalarArrayConverter $arrayToScalarArrayConverter,
        private FreshTimestampsFeature $freshTimestampsFeature,
    ) {
        //
    }

    public function handle(
        BulkModel $eloquent,
        Collection $collection,
        array $dateFields,
        array $events,
        bool $ignore,
        ?BulkCallback $creatingCallback,
    ): ?InsertBuilder {
        $result = new InsertBuilder();
        $result->into($eloquent->getTable())
            ->onConflictDoNothing($ignore);

        if ($creatingCallback === null) {
            $this->fillInBuilderFromCollection($result, $collection, $dateFields, $events);
        } else {
            $collection = $this->prepareModels($collection, $events);
            $collection = $creatingCallback->handle($collection) ?? $collection;

            if ($collection->isEmpty()) {
                $result->reset();

                return null;
            }

            $this->fillInBuilderFromArray(
                $result,
                $this->convertCollectionToArray($collection, $dateFields)
            );
        }

        return $result;
    }


    /**
     * @param Collection<BulkModel> $collection
     * @param array $events
     * @return Collection
     */
    private function prepareModels(Collection $collection, array $events): Collection
    {
        return $collection
            ->filter(
                fn (BulkModel $model) => $this->fireModelEvents($model, $events)
            )
            ->each(
                fn (BulkModel $model) => $this->freshTimestampsFeature->handle($model)
            );
    }

    private function fireModelEvents(BulkModel $model, array $events): bool
    {
        return $this->fireModelEventsFeature->handle($model, $events, [
            BulkEventEnum::SAVING,
            BulkEventEnum::CREATING,
        ]);
    }

    /**
     * @param Collection $collection
     * @param string[] $dateFields
     * @return array
     */
    private function convertCollectionToArray(Collection $collection, array $dateFields): array
    {
        return $collection
            ->transform(
                fn (BulkModel $model) => $this->arrayToScalarArrayConverter->handle(
                    $dateFields,
                    $model->getAttributes(),
                )
            )
            ->toArray();
    }

    private function fillInBuilderFromCollection(
        InsertBuilder $builder,
        Collection $collection,
        array $dateFields,
        array $events,
    ): void {
        $columns = [];

        foreach ($collection as $model) {
            if ($this->fireModelEvents($model, $events) === false) {
                continue;
            }

            $this->freshTimestampsFeature->handle($model);

            $row = $this->arrayToScalarArrayConverter->handle(
                $dateFields,
                $model->getAttributes(),
            );

            foreach ($row as $key => $value) {
                $columns[$key] = $key;
            }

            $builder->addValue($row);
        }

        $builder->columns($columns);
    }

    private function fillInBuilderFromArray(InsertBuilder $builder, array $rows): void
    {
        $columns = [];

        foreach ($rows as $row) {
            $builder->addValue($row);

            foreach ($row as $key => $value) {
                $columns[$key] = $key;
            }
        }

        $builder->columns($columns);
    }
}
