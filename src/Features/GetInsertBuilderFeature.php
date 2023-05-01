<?php

namespace Lapaliv\BulkUpsert\Features;

use Carbon\Carbon;
use DateTime;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;
use Lapaliv\BulkUpsert\Exceptions\BulkAttributeTypeIsNotScalar;
use stdClass;

/**
 * @internal
 */
class GetInsertBuilderFeature
{
    private DateTimeInterface $now;
    private string $createdAtColumn;
    private string $updatedAtColumn;
    private string $createdAt;
    private string $updatedAt;

    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        bool $ignore,
        array $dateFields,
        ?string $deletedAtColumn,
    ): ?InsertBuilder {
        $result = new InsertBuilder();
        $result->into($eloquent->getTable())->onConflictDoNothing($ignore);
        $columns = [];

        $this->createdAtColumn = $eloquent->getCreatedAtColumn();
        $this->updatedAtColumn = $eloquent->getUpdatedAtColumn();

        if ($eloquent->usesTimestamps()) {
            $now = Carbon::now();
            $this->createdAt = $now->format(
                $dateFields[$this->createdAtColumn] ?? 'Y-m-d H:i:s'
            );
            $this->updatedAt = $now->format(
                $dateFields[$this->updatedAtColumn] ?? 'Y-m-d H:i:s'
            );
        }

        foreach ($data->rows as $row) {
            if ($row->skipCreating) {
                continue;
            }

            $columns = [];
            $array = $this->convertModelToArray($row, $columns, $dateFields, $deletedAtColumn);
            $result->addValue($array);
        }

        if (empty($columns)) {
            return null;
        }

        return $result->columns($columns);
    }

    private function convertModelToArray(
        BulkAccumulationItemEntity $row,
        array &$columns,
        array $dateFields,
        ?string $deletedAtColumn,
    ): array {
        $result = [];

        foreach ($row->model->getAttributes() as $key => $value) {
            $columns[$key] = $key;

            if ($value !== null && isset($dateFields[$key])) {
                $date = $value instanceof DateTime ? $value : new DateTime($value);
                $result[$key] = $date->format($dateFields[$key]);

                continue;
            }

            if (is_object($value)) {
                if (PHP_VERSION_ID >= 80100 && enum_exists(get_class($value))) {
                    $value = $value->value;
                } elseif (method_exists($value, '__toString')) {
                    $value = $value->__toString();
                } elseif ($value instanceof stdClass) {
                    $value = (array) $value;
                } elseif (method_exists($value, 'toArray')) {
                    $value = $value->toArray();
                } elseif ($value instanceof CastsAttributes) {
                    $value = $value->set($row->model, $key, $value, $result);
                } else {
                    throw new BulkAttributeTypeIsNotScalar($key);
                }
            }

            $result[$key] = $value;
        }

        $this->freshTimestamps($result, $columns, $row, $deletedAtColumn);

        return $result;
    }

    private function freshTimestamps(
        array &$result,
        array &$columns,
        BulkAccumulationItemEntity $row,
        ?string $deletedAtColumn,
    ): void {
        if ($row->isDeleting && $row->skipDeleting) {
            $result[$deletedAtColumn] = null;
        }

        if ($row->model->usesTimestamps()) {
            if (!isset($columns[$this->createdAtColumn])) {
                $columns[$this->createdAtColumn] = $this->createdAtColumn;
                $result[$this->createdAtColumn] = $this->createdAt;
            }

            if (!isset($columns[$this->updatedAtColumn])) {
                $columns[$this->updatedAtColumn] = $this->updatedAtColumn;
                $result[$this->updatedAtColumn] = $this->updatedAt;
            }
        }
    }
}
