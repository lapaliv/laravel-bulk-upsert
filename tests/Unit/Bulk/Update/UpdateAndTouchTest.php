<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Lapaliv\BulkUpsert\Tests\App\Collection\PostCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\Post;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
final class UpdateAndTouchTest extends TestCase
{
    public function test(): void
    {
        // arrange
        $now = Carbon::now();
        Carbon::setTestNow(Carbon::now()->subYear());

        /** @var PostCollection $posts */
        $posts = MySqlPost::factory()
            ->count(2)
            ->create()
            ->each(
                function (Post $post) {
                    MySqlComment::factory()
                        ->count(2)
                        ->create(['post_id' => $post->id]);

                    $post->text = Str::random();
                }
            );
        $sut = MySqlPost::query()->bulk();
        Carbon::setTestNow($now);

        MySqlComment::setGlobalTouchedRelations(['user']);
        MySqlPost::setGlobalTouchedRelations(['comments']);

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
