<?php

namespace Lapaliv\BulkUpsert\Tests\App\Traits;

use Illuminate\Support\Facades\DB;
use JsonException;
use Lapaliv\BulkUpsert\Tests\App\Models\Entity;

trait CheckEntityInDatabase
{
    /**
     * @param Entity $entity
     * @param string[]|null $only
     * @param string[]|null $except
     * @return void
     * @throws JsonException
     */
    private function assertDatabaseHasEntity(Entity $entity, ?array $only = null, ?array $except = null): void
    {
        $this->assertDatabaseHas(
            $entity->getTable(),
            $this->convertEntityToArrayForAsserting($entity, $only, $except),
            $entity->getConnectionName(),
        );
    }

    /**
     * @param Entity $entity
     * @param string[]|null $only
     * @param string[]|null $except
     * @return void
     * @throws JsonException
     */
    private function assertDatabaseMissingEntity(Entity $entity, ?array $only = null, ?array $except = null): void
    {
        $this->assertDatabaseMissing(
            $entity->getTable(),
            $this->convertEntityToArrayForAsserting($entity, $only, $except),
            $entity->getConnectionName(),
        );
    }

    /**
     * @param Entity $entity
     * @param string[]|null $only
     * @param string[]|null $except
     * @return array<string, scalar>
     * @throws JsonException
     */
    private function convertEntityToArrayForAsserting(Entity $entity, ?array $only = null, ?array $except = null): array
    {
        $result = [
            'uuid' => $entity->uuid,
            'string' => $entity->string,
            'nullable_string' => $entity->nullable_string,
            'integer' => $entity->integer,
            'nullable_integer' => $entity->nullable_integer,
            'decimal' => $entity->decimal,
            'nullable_decimal' => $entity->nullable_decimal,
            'boolean' => $entity->boolean,
            'nullable_boolean' => $entity->nullable_boolean,
            'json' => DB::connection($entity->getConnectionName())->raw(
                sprintf("cast('%s' as json)", json_encode($entity->json, JSON_THROW_ON_ERROR))
            ),
            'nullable_json' => $entity->nullable_json === null
                ? null
                : DB::connection($entity->getConnectionName())->raw(
                    sprintf("cast('%s' as json)", json_encode($entity->nullable_json, JSON_THROW_ON_ERROR))
                ),
            'date' => $entity->date->toDateString(),
            'nullable_date' => $entity->nullable_date?->toDateString(),
            'custom_datetime' => $entity->custom_datetime->format(Entity::CUSTOM_DATE_FORMAT),
            'nullable_custom_datetime' => $entity->nullable_custom_datetime?->format(Entity::CUSTOM_DATE_FORMAT),
            'microseconds' => $entity->microseconds->format(Entity::MICROSECONDS_FORMAT),
            'nullable_microseconds' => $entity->nullable_microseconds?->format(Entity::MICROSECONDS_FORMAT),
        ];

        if ($only !== null) {
            $result = array_filter(
                $result,
                static fn (string $key) => in_array($key, $only, true),
                ARRAY_FILTER_USE_KEY
            );

            if ($entity->getIncrementing() && in_array($entity->getKeyName(), $only, true)) {
                $result[$entity->getKeyName()] = $entity->getKey();
            }
        } elseif ($except !== null) {
            $result = array_filter(
                $result,
                static fn (string $key) => in_array($key, $except, true) === false,
                ARRAY_FILTER_USE_KEY
            );

            if ($entity->getIncrementing() && in_array($entity->getKeyName(), $except, true) === false) {
                $result[$entity->getKeyName()] = $entity->getKey();
            }
        }

        return $result;
    }
}
