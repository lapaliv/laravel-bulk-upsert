<?php

namespace Lapaliv\BulkUpsert\Features;

/**
 * @deprecated
 */
class BulkCollapseArrayFeature
{
    /**
     * @param array<mixed, scalar[]|scalar> $array
     * @return array<int, scalar>
     */
    public function handle(array $array): array
    {
        $result = [];

        foreach ($array as $value) {
            if (is_array($value)) {
                foreach ($this->handle($value) as $subValue) {
                    $result[] = $subValue;
                }
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }
}
