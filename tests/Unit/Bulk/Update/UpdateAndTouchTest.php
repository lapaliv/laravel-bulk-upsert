<?php

namespace Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\App\Models\Comment;
use Tests\App\Models\Post;
use Tests\TestCaseWrapper;

/**
 * The touching of the related models when `update()` writes the rows.
 *
 * @internal
 */
final class UpdateAndTouchTest extends TestCaseWrapper
{
    /**
     * The touching goes down the chain of the relations and updates `updated_at` everywhere.
     *
     * @return void
     *
     * @throws \JsonException
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function test(): void
    {
        // arrange
        $now = Carbon::now();
        Carbon::setTestNow(Carbon::now()->subYear());

        /** @var \Tests\App\Collection\PostCollection $posts */
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
        /** @var \Tests\App\Models\Post $post */
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
