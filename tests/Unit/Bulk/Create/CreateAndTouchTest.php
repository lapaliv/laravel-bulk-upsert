<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\Comment;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\Post;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
final class CreateAndTouchTest extends TestCase
{
    /**
     * @param class-string<User> $userModel
     * @param class-string<Post> $postModel
     * @param class-string<Comment> $commentModel
     *
     * @return void
     *
     * @dataProvider modelsDataProvider
     */
    public function test(string $userModel, string $postModel, string $commentModel): void
    {
        // arrange
        $now = Carbon::now();
        Carbon::setTestNow(Carbon::now()->subYear());

        $posts = $postModel::factory()->count(2)->create();
        $users = $userModel::factory()->count(2)->create();

        $comments = new CommentCollection([
            $commentModel::factory()->make([
                'post_id' => $posts->get(0)->id,
                'user_id' => $users->get(0)->id,
            ]),
            $commentModel::factory()->make([
                'post_id' => $posts->get(1)->id,
                'user_id' => $users->get(1)->id,
            ]),
        ]);

        $sut = $commentModel::query()
            ->bulk()
            ->uniqueBy(['post_id', 'user_id']);
        Carbon::setTestNow($now);

        $commentModel::setGlobalTouchedRelations(['user', 'post']);
        $postModel::setGlobalTouchedRelations([]);

        // act
        $sut->create($comments);

        // assert
        /** @var Post $post */
        foreach ($posts as $post) {
            $this->assertDatabaseHas($post->getTable(), [
                'id' => $post->id,
                'updated_at' => Carbon::now()->toDateTimeString(),
            ], $post->getConnectionName());
        }

        foreach ($users as $user) {
            $this->assertDatabaseHas($user->getTable(), [
                'id' => $user->id,
                'updated_at' => Carbon::now()->toDateTimeString(),
            ], $user->getConnectionName());
        }
    }

    public function modelsDataProvider(): array
    {
        return [
            'mysql' => [
                MySqlUser::class,
                MySqlPost::class,
                MySqlComment::class,
            ],
            'postgresql' => [
                PostgreSqlUser::class,
                PostgreSqlPost::class,
                PostgreSqlComment::class,
            ],
        ];
    }
}
