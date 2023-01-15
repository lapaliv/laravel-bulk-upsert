<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Contracts\BulkModel;

class GetDateFieldsFeature
{
    /**
     * @return array<string, string>
     */
    public function handle(BulkModel $model): array
    {
        $result = [];


        foreach ($model->getDates() as $field) {
            $result[$field] = $model->getDateFormat();
        }

        if (method_exists($model, 'getDeletedAtColumn')) {
            $result[$model->getDeletedAtColumn()] = $model->getDateFormat();
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
