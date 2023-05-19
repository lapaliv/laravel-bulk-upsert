<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Illuminate\Support\Str;
use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Collection\PostCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\Comment;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\Post;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlPost;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
final class UpdateAndTouchTest extends TestCase
{
    /**
     * @param class-string<Post> $postModel
     * @param class-string<Comment> $commentModel
     *
     * @return void
     *
     * @throws JsonException
     * @throws BulkException
     *
     * @dataProvider modelsDataProvider
     */
    public function test(string $postModel, string $commentModel): void
    {
        // arrange
        $now = Carbon::now();
        Carbon::setTestNow(Carbon::now()->subYear());

        /** @var PostCollection $posts */
        $posts = $postModel::factory()
            ->count(2)
            ->create()
            ->each(
                function (Post $post) use ($commentModel): void {
                    $commentModel::factory()
                        ->count(2)
                        ->create(['post_id' => $post->id]);

                    $post->text = Str::random();
                }
            );
        $sut = $postModel::query()->bulk();
        Carbon::setTestNow($now);

        $commentModel::setGlobalTouchedRelations(['user']);
        $postModel::setGlobalTouchedRelations(['comments']);

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

    public function modelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlPost::class, MySqlComment::class],
            'postgre' => [PostgreSqlPost::class, PostgreSqlComment::class],
        ];
    }
}
