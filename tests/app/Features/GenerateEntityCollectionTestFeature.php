<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Exception;
use Lapaliv\BulkUpsert\Tests\App\Collections\EntityCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\Entity;

class GenerateEntityCollectionTestFeature
{
    public function __construct(
        private GenerateEntityTestFeature $generateEntityTestFeature,
    ) {
        // Nothing
    }

    /**
     * @param class-string<Entity> $model
     * @param int $limit
     * @param array|null $only
     * @param array $nullable
     * @return EntityCollection
     * @throws Exception
     */
    public function handle(string $model, int $limit, ?array $only = null, array $nullable = []): EntityCollection
    {
        $collection = (new $model())->newCollection();

        for ($i = 0; $i < $limit; $i++) {
            $collection->push(
                $this->generateEntityTestFeature->handle($model, $only, $nullable)
            );
        }

        return $collection;
    }
}
