<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenario;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Tests\App\Models\Article;
use Lapaliv\BulkUpsert\Tests\App\Models\Comment;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\Unit\BulkAccumulationEntityTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenarioTestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class TouchRelationsTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;

    /**
     * After creation, all touch relations should be recursively touched.
     *
     * @return void
     */
    public function test(): void
    {
        // arrange
        Carbon::setTestNow(Carbon::now()->startOfSecond());
        $user = User::factory()->create([
            'created_at' => Carbon::parse('2020-01-02 03:04:05'),
            'updated_at' => Carbon::parse('2020-01-02 03:04:05'),
        ]);
        $articles = Article::factory()->count(2)->create([
            'user_id' => $user->id,
            'created_at' => Carbon::parse('2020-01-02 03:04:05')->toDateTimeString(),
            'updated_at' => Carbon::parse('2020-01-02 03:04:05')->toDateTimeString(),
        ]);
        $eventDispatcher = new BulkEventDispatcher(Comment::class);
        $comments = Comment::factory()->count(2)->make([
            'user_id' => $user->id,
        ]);
        $data = $this->getBulkAccumulationEntityFromCollection($comments, ['user_id', 'text']);

        Comment::setGlobalTouchedRelations(['user']);
        User::setGlobalTouchedRelations(['articles']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        $this->assertDatabaseHas(
            $user->getTable(),
            [
                'id' => $user->id,
                'created_at' => $user->created_at->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ],
            $user->getConnectionName(),
        );

        foreach ($articles as $article) {
            $this->assertDatabaseHas(
                $article->getTable(),
                [
                    'uuid' => $article->uuid,
                    'user_id' => $user->id,
                    'title' => $article->title,
                    'created_at' => $article->created_at->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ],
                $article->getConnectionName(),
            );
        }
    }
}
