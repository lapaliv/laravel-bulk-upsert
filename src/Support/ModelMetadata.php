<?php

namespace Lapaliv\BulkUpsert\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Reads the metadata of an Eloquent model which the package relies on.
 *
 * It gives the date(time) fields with their formats and the soft-delete column.
 *
 * @internal
 */
class ModelMetadata
{
    /**
     * Get the date(time) fields of the model with their formats.
     *
     * In the result the key is the name of the field and the value is its format. The
     * fields come from the dates of the model, from the given soft-delete column and
     * from the `date` and `datetime` casts.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string|null $deletedAtColumn
     *
     * @return array<string, string>
     */
    public function dateFields(Model $model, ?string $deletedAtColumn): array
    {
        $result = [];

        foreach ($model->getDates() as $field) {
            $result[(string)$field] = $model->getDateFormat();
        }

        if ($deletedAtColumn !== null) {
            $result[$deletedAtColumn] = $model->getDateFormat();
        }

        /** @psalm-suppress MixedAssignment */
        foreach ($model->getCasts() as $key => $value) {
            if (is_string($value) && preg_match('/^(date(?:time)?)(?::(.+?))?$/', $value, $matches)) {
                $key = (string)$key;

                if ($matches[1] === 'date') {
                    $result[$key] = $matches[2] ?? 'Y-m-d';
                } else {
                    $result[$key] = $matches[2] ?? $model->getDateFormat();
                }
            }
        }

        return $result;
    }

    /**
     * Get the name of the soft-delete column of the model.
     *
     * The method returns `null` when the model does not support soft deleting.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return string|null
     */
    public function deletedAtColumn(Model $model): ?string
    {
        return method_exists($model, 'getDeletedAtColumn')
            ? (string)$model->getDeletedAtColumn()
            : null;
    }
}
