<?php

namespace Lapaliv\BulkUpsert\Converters;

use BackedEnum;
use Carbon\Carbon;
use DateTime;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Exceptions\BulkAttributeTypeIsNotScalar;
use stdClass;
use Stringable;

/**
 * Converts the attributes of a model into values which the database can accept.
 *
 * @internal
 */
readonly class AttributesToScalarArrayConverter
{
    /**
     * Convert the given attributes into scalar values.
     *
     * The values of the date fields are formatted with the format from `$dateFields`.
     * The objects are converted in the following way:
     * - a backed enum becomes its value;
     * - an object with `__toString()` becomes a string;
     * - a `stdClass` or an object with `toArray()` becomes an array;
     * - a cast object becomes the result of its `set()` method;
     * - any other `DateTimeInterface` becomes a `Y-m-d H:i:s` string.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array<string, mixed> $attributes
     * @param array<string, string> $dateFields
     *
     * @return array<string, mixed>
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkAttributeTypeIsNotScalar
     * @throws \DateMalformedStringException
     */
    public function handle(Model $model, array $attributes, array $dateFields): array
    {
        $result = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($attributes as $key => $value) {
            if ($value !== null && array_key_exists($key, $dateFields)) {
                if ($dateFields[$key] === 'Y-m-d') {
                    // A date-only column keeps the date part only.
                    // The raw value is in the storage format of the model (`setAttribute()`
                    // runs `fromDateTime()` for the date casts), so it is read by that format.
                    $date = $value instanceof DateTimeInterface
                        ? $value
                        : (Carbon::canBeCreatedFromFormat((string)$value, $model->getDateFormat())
                            ? Carbon::createFromFormat($model->getDateFormat(), (string)$value)
                            : new DateTime((string)$value));
                    $result[$key] = $date?->format('Y-m-d');
                } elseif ($value instanceof DateTimeInterface) {
                    // A date object is turned into the storage format of the model, as
                    // `Model::setAttribute()` does with `fromDateTime()`.
                    $result[$key] = $model->fromDateTime($value);
                } else {
                    // A string is a raw attribute which Eloquent has already prepared for the
                    // storage (or keeps as it is, like for `datetime:...` casts), so it is
                    // written as `Model::save()` writes it.
                    $result[$key] = $value;
                }

                continue;
            }

            if (!is_object($value)) {
                $result[$key] = $value;

                continue;
            }

            if ($value instanceof BackedEnum) {
                $result[$key] = $value->value;
            } elseif ($value instanceof Stringable) {
                $result[$key] = $value->__toString();
            } elseif ($value instanceof stdClass) {
                $result[$key] = (array)$value;
            } elseif (method_exists($value, 'toArray')) {
                /** @psalm-suppress MixedAssignment */
                $result[$key] = $value->toArray();
            } elseif ($value instanceof CastsAttributes) {
                /** @psalm-suppress MixedAssignment */
                $result[$key] = $value->set($model, $key, $value, $result);
            } elseif ($value instanceof DateTimeInterface) {
                $result[$key] = $value->format('Y-m-d H:i:s');
            } else {
                throw new BulkAttributeTypeIsNotScalar($key);
            }
        }

        return $result;
    }
}
