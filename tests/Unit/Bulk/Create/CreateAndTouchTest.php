<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Collection\CommentCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\Comment;
use Lapaliv\BulkUpsert\Tests\App\Models\Post;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
final class CreateAndTouchTest extends TestCase
{
    /**
     * @return void
     *
     * @throws BulkException
     */
    public function test(): void
    {
        // arrange
        $now = Carbon::now();
        Carbon::setTestNow(Carbon::now()->subYear());

        $posts = Post::factory()->count(2)->create();
        $users = User::factory()->count(2)->create();

        $comments = new CommentCollection([
            Comment::factory()->make([
                'post_id' => $posts->get(0)->id,
                'user_id' => $users->get(0)->id,
            ]),
            Comment::factory()->make([
                'post_id' => $posts->get(1)->id,
                'user_id' => $users->get(1)->id,
            ]),
        ]);

        $sut = Comment::query()
            ->bulk()
            ->uniqueBy(['post_id', 'user_id']);
        Carbon::setTestNow($now);

        Comment::setGlobalTouchedRelations(['user', 'post']);
        Post::setGlobalTouchedRelations([]);

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
}
