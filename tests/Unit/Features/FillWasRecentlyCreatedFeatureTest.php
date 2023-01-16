<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Features\FillWasRecentlyCreatedFeature;
use Lapaliv\BulkUpsert\Tests\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\Models\MysqlArticle;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

class FillWasRecentlyCreatedFeatureTest extends TestCase
{
    private GenerateUserCollectionFeature $generateUserCollectionFeature;

    /**
     * @return void
     * @throws Exception
     */
    public function testByPrimary(): void
    {
        // arrange
        $users = $this->generateUserCollectionFeature->handle(MysqlUser::class, 5, ['email'])
            ->each(
                fn (MysqlUser $user, int $key) => $user->id = $key
            );

        /** @var FillWasRecentlyCreatedFeature $sut */
        $sut = $this->app->make(FillWasRecentlyCreatedFeature::class);

        // act
        $sut->handle(new MysqlUser(), $users, [], 3, Carbon::now());

        // assert
        $users->each(
            function (MysqlUser $user) {
                self::assertEquals($user->id >= 3, $user->wasRecentlyCreated);
            }
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testByCreatedAt(): void
    {
        // arrange
        Carbon::setTestNow(Carbon::now()->startOfMinute());

        $users = new Collection([
            // users with created_at in the past
            ...$this->generateUserCollectionFeature->handle(MysqlArticle::class, 2, ['email'])
                ->each(
                    fn (MysqlArticle $user, int $key) => $user->created_at = Carbon::now()->subSecond(),
                ),
            // users with created_at equals now
            ...$this->generateUserCollectionFeature->handle(MysqlArticle::class, 2, ['email'])
                ->each(
                    fn (MysqlArticle $user, int $key) => $user->created_at = Carbon::now(),
                ),
            // users with created_at in the future
            ...$this->generateUserCollectionFeature->handle(MysqlArticle::class, 2, ['email'])
                ->each(
                    fn (MysqlArticle $user, int $key) => $user->created_at = Carbon::now()->addSecond(),
                ),
        ]);

        /** @var FillWasRecentlyCreatedFeature $sut */
        $sut = $this->app->make(FillWasRecentlyCreatedFeature::class);

        // act
        $sut->handle(new MysqlArticle(), $users, [], 1, Carbon::now());

        // assert
        $users->each(
            function (MysqlArticle $user) {
                self::assertEquals($user->created_at->gte(Carbon::now()), $user->wasRecentlyCreated);
            }
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateUserCollectionFeature = $this->app->make(GenerateUserCollectionFeature::class);
    }
}
