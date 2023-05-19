<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Features\GetDateFieldsFeature;
use Lapaliv\BulkUpsert\Features\GetDeletedAtColumnFeature;

trait BulkBuilderTrait
{
    public function bulk(): Bulk
    {
        return new Bulk($this->getModel());
    }

    /**
     * Selects a chunk of rows, fill in the values and update these rows.
     *
     * @param array $values
     * @param array|string|null $unique
     * @param int $chunk
     *
     * @return int
     */
    public function selectAndUpdateMany(
        array $values,
        string|array $unique = null,
        int $chunk = 100
    ): int {
        $model = $this->getModel();
        $eventDispatcher = new BulkEventDispatcher($model);

        // no events
        if (!$eventDispatcher->hasListeners(BulkEventEnum::model())
            && !$eventDispatcher->hasListeners(BulkEventEnum::collection())
        ) {
            $converter = new AttributesToScalarArrayConverter();
            $getDeletedAtColumnFeature = new GetDeletedAtColumnFeature();
            $getDateFieldsFeature = new GetDateFieldsFeature();

            $result = $this->update(
                $converter->handle(
                    $model,
                    $values,
                    $getDateFieldsFeature->handle(
                        $model,
                        $getDeletedAtColumnFeature->handle($model)
                    )
                )
            );

            unset($converter, $getDeletedAtColumnFeature, $getDateFieldsFeature);

            return $result;
        }

        $unique ??= [$model->getKeyName()];
        $unique = (array) $unique;
        $bulk = $this->bulk()
            ->chunk($chunk)
            ->uniqueBy($unique);
        $result = 0;

        if (in_array($model->getKeyName(), $unique) && $model->getIncrementing()) {
            $this->chunkById(
                $chunk,
                function (Collection $collection) use ($bulk, $values, &$result) {
                    $collection->each(
                        function (Model $model) use ($values) {
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

            if (empty($this->getQuery()->orders)) {
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
