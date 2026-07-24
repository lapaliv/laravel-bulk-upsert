<?php

namespace Lapaliv\BulkUpsert\Converters;

use BackedEnum;
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
                $date = $value instanceof DateTime ? $value : new DateTime((string)$value);
                $result[$key] = $date->format($dateFields[$key]);

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
