<?php

namespace Lapaliv\BulkUpsert\Converters;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use stdClass;

class CollectionToScalarArraysConverter
{
    public function __construct(
        private AttributesToScalarArrayConverter $arrayToScalarArrayConverter
    ) {
        // Nothing
    }

    /**
     * @param array<int, stdClass> $rows
     * @return array<int, array<string, scalar>>
     */
    public function handle(iterable $rows, array $dateFields = []): array
    {
        $result = [];

        foreach ($rows as $key => $value) {
            $attributes = $value instanceof BulkModel
                ? $value->getAttributes()
                : (array)$value;

            $result[$key] = $this->arrayToScalarArrayConverter->handle($dateFields, $attributes);
        }

        return $result;
    }
}
