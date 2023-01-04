<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\BulkModel;

class BulkGetDateFieldsFeature
{
    public function __construct(private BulkModel $model)
    {
        //
    }

    /**
     * @return array<string, string>
     */
    public function handle(): array
    {
        $result = [];

        foreach ($this->model->getDates() as $field) {
            $result[$field] = $this->model->getDateFormat();
        }

        foreach ($this->model->getCasts() as $key => $value) {
            if (is_string($value) && preg_match('/^(date(?:time)?)(?::(.+?))?$/', $value, $matches)) {
                if ($matches[1] === 'date') {
                    $result[$key] = $matches[2] ?? 'Y-m-d';
                } else {
                    $result[$key] = $matches[2] ?? $this->model->getDateFormat();
                }
            }
        }

        return $result;
    }
}