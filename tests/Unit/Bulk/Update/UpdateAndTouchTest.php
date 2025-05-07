<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Illuminate\Support\Str;
use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Collection\PostCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\Comment;
use Lapaliv\BulkUpsert\Tests\App\Models\Post;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
final class UpdateAndTouchTest extends TestCase
{
    /**
     * @return void
     *
     * @throws JsonException
     * @throws BulkException
     */
    public function test(): void
    {
        // arrange
        $now = Carbon::now();
        Carbon::setTestNow(Carbon::now()->subYear());

        /** @var PostCollection $posts */
        $posts = Post::factory()
            ->count(2)
            ->create()
            ->each(
                function (Post $post): void {
                    Comment::factory()
                        ->count(2)
                        ->create(['post_id' => $post->id]);

                    $post->text = Str::random();
                }
            );
        $sut = Post::query()->bulk();
        Carbon::setTestNow($now);

        Comment::setGlobalTouchedRelations(['user']);
        Post::setGlobalTouchedRelations(['comments']);

        // act
        $sut->update($posts);

        // assert
        /** @var Post $post */
        foreach ($posts as $post) {
            foreach ($post->comments as $comment) {
                $this->assertDatabaseHas($comment->getTable(), [
                    'id' => $comment->id,
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ], $comment->getConnectionName());

                $this->assertDatabaseHas($comment->user->getTable(), [
                    'id' => $comment->user_id,
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ], $comment->user->getConnectionName());
            }
        }
    }
}
