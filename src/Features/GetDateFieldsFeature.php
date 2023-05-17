<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Model;

class GetDateFieldsFeature
{
    public function handle(Model $model, ?string $deletedAtColumn): array
    {
        $result = [];

        foreach ($model->getDates() as $field) {
            $result[$field] = $model->getDateFormat();
        }

        if ($deletedAtColumn !== null) {
            $result[$deletedAtColumn] = $model->getDateFormat();
        }

        foreach ($model->getCasts() as $key => $value) {
            if (is_string($value) && preg_match('/^(date(?:time)?)(?::(.+?))?$/', $value, $matches)) {
                if ($matches[1] === 'date') {
                    $result[$key] = $matches[2] ?? 'Y-m-d';
                } else {
                    $result[$key] = $matches[2] ?? $model->getDateFormat();
                }
            }
        }

        return $result;
    }
}
