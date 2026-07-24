<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Support\ModelMetadata;

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
     * When the model has no listener of the package events, the method runs a plain
     * update query instead and returns the number of the affected rows.
     *
     * @param array<string, mixed> $values
     * @param string|string[]|null $unique
     * @param int $chunk
     *
     * @return int
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
     */
    public function selectAndUpdateMany(
        array $values,
        string|array|null $unique = null,
        int $chunk = 100
    ): int
    {
        $model = $this->getModel();
        $eventDispatcher = new BulkEventDispatcher($model);

        // no events
        if (!$eventDispatcher->hasListeners(BulkEventEnum::model())
            && !$eventDispatcher->hasListeners(BulkEventEnum::collection())
        ) {
            $converter = new AttributesToScalarArrayConverter();
            $modelMetadata = new ModelMetadata();

            $result = $this->update(
                $converter->handle(
                    $model,
                    $values,
                    $modelMetadata->dateFields(
                        $model,
                        $modelMetadata->deletedAtColumn($model)
                    )
                )
            );

            unset($converter, $modelMetadata);

            return $result;
        }

        $unique ??= [$model->getKeyName()];
        $unique = (array)$unique;
        $bulk = $this->bulk()
            ->chunk($chunk)
            ->uniqueBy($unique);
        $result = 0;

        if (in_array($model->getKeyName(), $unique) && $model->getIncrementing()) {
            $this->chunkById(
                $chunk,
                function (SupportCollection $collection) use ($bulk, $values, &$result): void {
                    $collection->each(
                        function (Model $model) use ($values): void {
                            /** @psalm-suppress MixedAssignment  */
                            foreach ($values as $attribute => $value) {
                                $model->setAttribute($attribute, $value);
                            }
                        }
                    );

                    $result += $collection->count();
                    $bulk->update($collection);
                },
                $model->getKeyName()
            );
        } else {
            $offset = 0;
            $this->limit($chunk);

            $orders = $this->getQuery()->orders;

            if ($orders === null || $orders === []) {
                foreach ($unique as $field) {
                    $this->orderBy($field);
                }
            }

            $builder = clone $this;

            while (true) {
                $collection = $builder->offset($offset)->get();

                if ($collection->isEmpty()) {
                    break;
                }

                $collection->each(
                    function (Model $model) use ($values) {
                        /** @psalm-suppress MixedAssignment */
                        foreach ($values as $attribute => $value) {
                            $model->setAttribute($attribute, $value);
                        }
                    }
                );

                $bulk->update($collection);

                $result += $collection->count();

                if ($collection->count() < $chunk) {
                    break;
                }

                $offset += $collection->count();
            }
        }

        return $result;
    }
}
