<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Contracts\BulkModel;

class GetDirtyAttributesFeature
{
    /**
     * Laravel has quite strange logic.
     * If the attribute is null then laravel thinks that it's not dirty.
     *
     * @param BulkModel $model
     * @return array
     * @see \Illuminate\Database\Eloquent\Concerns\HasAttributes::originalIsEquivalent() line 1953
     */
    public function handle(BulkModel $model): array
    {
        $result = $model->getDirty();
        $attributes = $model->getAttributes();
        $originals = $model->getOriginal();

        foreach ($model->getAttributes() as $key => $value) {
            if (array_key_exists($key, $result)) {
                continue;
            }

            if (array_key_exists($key, $attributes) === false) {
                continue;
            }

            if (array_key_exists($key, $originals) === false) {
                $result[$key] = $value;
                continue;
            }

            $attribute = $attributes[$key];
            $original = $attributes[$key];

            if ($attribute !== $original) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
