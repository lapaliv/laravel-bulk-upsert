<?php

namespace Tests\Unit\Scenarios\Shared;

use Carbon\Carbon;
use Tests\App\Models\Article;
use Tests\App\Models\Comment;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * Creating rows recursively touches the configured related models.
 *
 * @internal
 */
final class TouchRelationsStepTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * Creating comments touches their user, and the user in turn touches its articles.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
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
        $comments = Comment::factory()->count(2)->make([
            'user_id' => $user->id,
        ]);

        Comment::setGlobalTouchedRelations(['user']);
        User::setGlobalTouchedRelations(['articles']);

        // act
        Comment::query()
            ->bulk()
            ->uniqueBy(['user_id', 'text'])
            ->create($comments);

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
