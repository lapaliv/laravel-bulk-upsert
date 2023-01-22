<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Lapaliv\BulkUpsert\Tests\App\Collections\EntityCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\Entity;

class SaveAndFillEntityCollectionTestFeature
{
    public function __construct(
        private GenerateEntityTestFeature $generateEntityTestFeature
    ) {
        // Nothing
    }

    public function handle(EntityCollection $collection, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            /** @var Entity $entity */
            $entity = $collection->get($i);
            $entity->save();

            $raw = $this->generateEntityTestFeature->handle(get_class($entity));
            $entity->string = $raw->string;
            $entity->nullable_string = $raw->nullable_string;
            $entity->integer = $raw->integer;
            $entity->nullable_integer = $raw->nullable_integer;
            $entity->decimal = round($raw->decimal, 3);
            $entity->nullable_decimal = $raw->nullable_decimal;
            $entity->boolean = $raw->boolean;
            $entity->nullable_boolean = $raw->nullable_boolean;
            $entity->json = $raw->json;
            $entity->nullable_json = $raw->nullable_json;
            $entity->date = $raw->date;
            $entity->nullable_date = $raw->nullable_date;
            $entity->custom_datetime = $raw->custom_datetime;
            $entity->nullable_custom_datetime = $raw->nullable_custom_datetime;
            $entity->microseconds = $raw->microseconds;
            $entity->nullable_microseconds = $raw->nullable_microseconds;
        }
    }
}
