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
            // The suffix of `date:...` / `datetime:...` is the serialization format only:
            // Laravel stores every date with `fromDateTime()`, i.e. with `getDateFormat()`.
            if (is_string($value) && preg_match('/^(?:immutable_)?(date(?:time)?)(?::.+)?$/', $value, $matches)) {
                $result[(string)$key] = $matches[1] === 'date' ? 'Y-m-d' : $model->getDateFormat();
            }
        }

        return $result;
    }

    /**
     * Get the names of the columns which are cast to a date without the time.
     *
     * The casts are read as they are declared: Laravel turns `date:...` into a custom
     * datetime cast, so `hasCast()` cannot tell a date from a datetime. The storage
     * format of the model says nothing about it either, since it is shared by all the
     * date columns.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return array<string, true>
     */
    public function dateOnlyFields(Model $model): array
    {
        $result = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($model->getCasts() as $key => $value) {
            if (is_string($value) && preg_match('/^(?:immutable_)?date(?::.+)?$/', $value)) {
                $result[(string)$key] = true;
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
