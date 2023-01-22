<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Exception;
use Lapaliv\BulkUpsert\Tests\App\Collections\UserCollection;

class GenerateUserCollectionTestFeature
{
    public function __construct(
        private GenerateUserTestFeature $generateUserFeature,
    ) {
        // Nothing
    }

    /**
     * @param string $model
     * @param int $limit
     * @param array|null $only
     * @param array $nullable
     * @return UserCollection
     * @throws Exception
     */
    public function handle(string $model, int $limit, ?array $only = null, array $nullable = []): UserCollection
    {
        $collection = new UserCollection();

        for ($i = 0; $i < $limit; $i++) {
            $collection->push(
                $this->generateUserFeature->handle($model, $only, $nullable)
            );
        }

        return $collection;
    }
}
