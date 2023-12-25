<?php

namespace Lapaliv\BulkUpsert\Tests\Unit;

use Lapaliv\BulkUpsert\Tests\App\Collection\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteArticle;

trait ArticleTestTrait
{
    /**
     * The models for checking.
     *
     * @return array[]
     */
    public function articleModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlArticle::class],
            'psql' => [PostgreSqlArticle::class],
            'sqlite' => [SqLiteArticle::class],
        ];
    }

    protected function makeArticleCollection(string $model, int $count, array $data = []): ArticleCollection
    {
        return call_user_func([$model, 'factory'])->count($count)->make($data);
    }

    protected function createArticleCollection(string $model, int $count, array $data = []): ArticleCollection
    {
        return call_user_func([$model, 'factory'])->count($count)->create($data);
    }

    protected function createDirtyArticleCollection(string $model, int $count, array $data = []): ArticleCollection
    {
        $users = $this->createArticleCollection($model, $count);
        $result = $this->makeArticleCollection($model, $count, $data);

        foreach ($result as $key => $user) {
            $user->email = $users->get($key)->email;
        }

        return $result;
    }
}
