<?php

namespace Lapaliv\BulkUpsert\Tests\Unit;

use Lapaliv\BulkUpsert\Tests\App\Collection\StoryCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlStory;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlStory;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteStory;

trait StoryTestTrait
{
    /**
     * The models for checking.
     *
     * @return array[]
     */
    public function storyModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlStory::class],
            'psql' => [PostgreSqlStory::class],
            'sqlite' => [SqLiteStory::class],
        ];
    }

    protected function makeStoryCollection(string $model, int $count, array $data = []): StoryCollection
    {
        return call_user_func([$model, 'factory'])->count($count)->make($data);
    }

    protected function createStoryCollection(string $model, int $count, array $data = []): StoryCollection
    {
        return call_user_func([$model, 'factory'])->count($count)->create($data);
    }

    protected function createDirtyStoryCollection(string $model, int $count, array $data = []): StoryCollection
    {
        $users = $this->createStoryCollection($model, $count);
        $result = $this->makeStoryCollection($model, $count, $data);

        foreach ($result as $key => $user) {
            $user->email = $users->get($key)->email;
        }

        return $result;
    }
}
