<?php

namespace Lapaliv\BulkUpsert\Tests\Unit;

use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteComment;

trait CommentTestTrait
{
    /**
     * The models for checking.
     *
     * @return array[]
     */
    public function commentModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlComment::class],
            'psql' => [PostgreSqlComment::class],
            'sqlite' => [SqLiteComment::class],
        ];
    }

    protected function makeCommentCollection(string $model, int $count, array $data = []): CommentCollection
    {
        return call_user_func([$model, 'factory'])->count($count)->make($data);
    }

    protected function createCommentCollection(string $model, int $count, array $data = []): CommentCollection
    {
        return call_user_func([$model, 'factory'])->count($count)->create($data);
    }

    protected function createDirtyCommentCollection(string $model, int $count, array $data = []): CommentCollection
    {
        $users = $this->createCommentCollection($model, $count);
        $result = $this->makeCommentCollection($model, $count, $data);

        foreach ($result as $key => $user) {
            $user->email = $users->get($key)->email;
        }

        return $result;
    }
}
