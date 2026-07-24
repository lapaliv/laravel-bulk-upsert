<?php

namespace Lapaliv\BulkUpsert\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Converters\MixedValueToScalarConverter;

/**
 * Builds a scalar hash key from the unique attributes of a row, so that rows coming
 * from the memory and rows coming from the database can be matched with each other.
 *
 * @internal
 */
readonly class UniqueKeyGenerator
{
    /**
     * Create a new key generator instance.
     *
     * @param \Lapaliv\BulkUpsert\Support\ValueHasher $valueHasher
     * @param \Lapaliv\BulkUpsert\Converters\MixedValueToScalarConverter $mixedValueToScalarConverter
     *
     * @return void
     */
    public function __construct(
        private ValueHasher $valueHasher,
        private MixedValueToScalarConverter $mixedValueToScalarConverter,
    )
    {
        //
    }

    /**
     * Build the hash key of the row from the given attributes.
     *
     * The values of the attributes are joined with a colon and then hashed. The values
     * of a model are taken with `getAttribute()`.
     *
     * @param array<string, scalar>|\Illuminate\Database\Eloquent\Model $row
     * @param string[] $attributes
     *
     * @return string
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
     */
    public function handle(array|Model $row, array $attributes): string
    {
        $key = '';

        foreach ($attributes as $attribute) {
            if ($row instanceof Model) {
                $key .= (string)$this->mixedValueToScalarConverter->handle(
                    $row->getAttribute($attribute)
                );
            } else {
                $key .= (string)$row[$attribute];
            }

            $key .= ':';
        }

        return $this->valueHasher->handle($key);
    }

    /**
     * Key the given rows by the hash of their unique attributes.
     *
     * @param array<int, array<string, scalar>>|\Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $rows
     * @param string[] $attributes
     *
     * @return array<string, \Illuminate\Database\Eloquent\Model|scalar[]>
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
     */
    public function keyBy(array|Collection $rows, array $attributes): array
    {
        $result = [];

        foreach ($rows as $row) {
            $key = $this->handle($row, $attributes);
            $result[$key] = $row;
        }

        return $result;
    }
}
