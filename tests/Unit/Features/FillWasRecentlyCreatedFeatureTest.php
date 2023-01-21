<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Features\FillWasRecentlyCreatedFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

class FillWasRecentlyCreatedFeatureTest extends TestCase
{
    private GenerateUserCollectionTestFeature $generateUserCollectionFeature;

    /**
     * @return void
     * @throws Exception
     */
    public function testByPrimary(): void
    {
        // arrange
        $users = $this->generateUserCollectionFeature->handle(MySqlUser::class, 5, ['email'])
            ->each(
                fn(MySqlUser $user, int $key) => $user->id = $key
            );

        /** @var FillWasRecentlyCreatedFeature $sut */
        $sut = $this->app->make(FillWasRecentlyCreatedFeature::class);

        // act
        $sut->handle(new MySqlUser(), $users, [], 3, Carbon::now());

        // assert
        $users->each(
            function (MySqlUser $user) {
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
            ...$this->generateUserCollectionFeature->handle(MySqlUser::class, 2, ['email'])
                ->each(
                    fn(MySqlUser $user, int $key) => $user->created_at = Carbon::now()->subDay(),
                ),
            // users with created_at equals now
            ...$this->generateUserCollectionFeature->handle(MySqlUser::class, 2, ['email'])
                ->each(
                    fn(MySqlUser $user, int $key) => $user->created_at = Carbon::now(),
                ),
            // users with created_at in the future
            ...$this->generateUserCollectionFeature->handle(MySqlUser::class, 2, ['email'])
                ->each(
                    fn(MySqlUser $user, int $key) => $user->created_at = Carbon::now()->addDay(),
                ),
        ]);

        /** @var FillWasRecentlyCreatedFeature $sut */
        $sut = $this->app->make(FillWasRecentlyCreatedFeature::class);

        // act
        $sut->handle(new MySqlUser(), $users, [], null, Carbon::now());

        // assert
        $users->each(
            function (MySqlUser $user) {
                if ($user->created_at->gte(Carbon::now()) !== $user->wasRecentlyCreated) {
                    dd(
                        $user->created_at->format('Y-m-d H:i:s.u'),
                        Carbon::now()->format('Y-m-d H:i:s.u'),
                        $user->wasRecentlyCreated
                    );
                }
                self::assertEquals($user->created_at->gte(Carbon::now()), $user->wasRecentlyCreated);
            }
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateUserCollectionFeature = $this->app->make(GenerateUserCollectionTestFeature::class);
    }
}
