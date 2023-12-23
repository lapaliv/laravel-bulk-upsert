<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenario;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Tests\App\Models\Article;
use Lapaliv\BulkUpsert\Tests\App\Models\Comment;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteComment;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\Unit\ArticleTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\BulkAccumulationEntityTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\CommentTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenarioTestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class TouchRelationsTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use CommentTestTrait;
    use ArticleTestTrait;

    /**
     * After creation, all touch relations should be recursively touched.
     *
     * @param string $userModel
     *
     * @psalm-param class-string<User> $userModel
     *
     * @param string $commentModel
     *
     * @psalm-param class-string<Comment> $commentModel
     *
     * @param string $articleModel
     *
     * @psalm-param class-string<Article> $articleModel
     *
     * @return void
     *
     * @dataProvider connectionsDataProvider
     */
    public function test(string $userModel, string $commentModel, string $articleModel): void
    {
        // arrange
        Carbon::setTestNow(Carbon::now()->startOfSecond());
        $user = $this->createUser($userModel, [
            'created_at' => Carbon::parse('2020-01-02 03:04:05'),
            'updated_at' => Carbon::parse('2020-01-02 03:04:05'),
        ]);
        $articles = $this->createArticleCollection($articleModel, 2, [
            'user_id' => $user->id,
            'created_at' => Carbon::parse('2020-01-02 03:04:05')->toDateTimeString(),
            'updated_at' => Carbon::parse('2020-01-02 03:04:05')->toDateTimeString(),
        ]);
        $eventDispatcher = new BulkEventDispatcher($commentModel);
        $comments = $this->makeCommentCollection($commentModel, 2, [
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

    /**
     * Data provider.
     *
     * @return array[]
     */
    public function connectionsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class, MySqlComment::class, MySqlArticle::class],
            'pgsql' => [PostgreSqlUser::class, PostgreSqlComment::class, PostgreSqlArticle::class],
            'sqlite' => [SqLiteUser::class, SqLiteComment::class, SqLiteArticle::class],
        ];
    }
}
