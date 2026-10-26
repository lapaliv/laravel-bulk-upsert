<?php

namespace Lapaliv\BulkUpsert\Converters;

use BackedEnum;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lapaliv\BulkUpsert\Exceptions\BulkAttributeTypeIsNotScalar;
use Lapaliv\BulkUpsert\Support\ModelMetadata;
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
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public function handle(Model $model, array $attributes, array $dateFields): array
    {
        $result = [];
        $dateOnlyFields = null;

        /** @psalm-suppress MixedAssignment */
        foreach ($attributes as $key => $value) {
            if ($value !== null && array_key_exists($key, $dateFields)) {
                // The date-only columns come from the declared casts, not from the format:
                // the storage format of the model is shared by all its date columns.
                $dateOnlyFields ??= (new ModelMetadata())->dateOnlyFields($model);

                if (array_key_exists($key, $dateOnlyFields)) {
                    // A date-only column keeps the date part only.
                    $result[$key] = $this->toDateTime($model, $value)->format('Y-m-d');
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

    /**
     * Read the raw value of a date column, as `HasAttributes::asDateTime()` reads it.
     *
     * `asDateTime()` itself is protected. A numeric value is a Unix timestamp, which is
     * read in the default timezone of the application; reading it in UTC would move the
     * calendar day near midnight.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param mixed $value
     *
     * @return \DateTimeInterface
     *
     * @throws \Carbon\Exceptions\InvalidFormatException When the value is not a date.
     */
    private function toDateTime(Model $model, mixed $value): DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value, date_default_timezone_get());
        }

        $value = (string)$value;

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value) === 1) {
            // Carbon 2 gives `false` and Carbon 3 gives `null` when the format does not match.
            $date = Carbon::createFromFormat('Y-m-d', $value);

            return $date instanceof Carbon ? $date->startOfDay() : Carbon::parse($value);
        }

        try {
            $date = Carbon::createFromFormat($model->getDateFormat(), $value);
        } catch (InvalidArgumentException) {
            $date = null;
        }

        return $date instanceof DateTimeInterface ? $date : Carbon::parse($value);
    }
}
