<?php

namespace Lapaliv\BulkUpsert\Tests\Unit;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Exceptions\BulkModelIsUndefined;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Support\Callback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Mockery;
use Mockery\VerificationDirector;
use stdClass;

class BulkUpdateTest extends TestCase
{
    private GenerateUserCollectionFeature $generateUserCollectionFeature;

    /**
     * @dataProvider chunkCallbackDataProvider
     * @param string $model
     * @param int $numberOfUsers
     * @param int $chunkSize
     * @return void
     * @throws Exception
     */
    public function testChunkCallback(string $model, int $numberOfUsers, int $chunkSize): void
    {
        // arrange
        $users = $this->generateUserCollectionFeature->handle($model, $numberOfUsers, ['email', 'name']);
        $callbackSpy = Mockery::spy(Callback::class);

        /** @var BulkUpdate $sut */
        $sut = $this->app
            ->make(BulkUpdate::class)
            ->chunk($chunkSize, $callbackSpy);

        // act
        $sut->update($model, $users, ['email']);

        // assert
        $callbackSpy->shouldHaveBeenCalled();
        /** @var VerificationDirector $method */
        $method = $callbackSpy->shouldHaveReceived('__invoke');
        $method->times((int)ceil($numberOfUsers / $chunkSize))
            ->withArgs(
                function (...$args) use ($chunkSize): bool {
                    self::assertCount(1, $args);
                    self::assertInstanceOf(Collection::class, $args[0]);
                    self::assertLessThanOrEqual($chunkSize, $args[0]->count());

                    return true;
                }
            );
    }

    /**
     * @param string $model
     * @return void
     * @dataProvider throwBulkModelIsUndefinedDataProvider
     */
    public function testThrowBulkModelIsUndefined(string $model): void
    {
        // assert
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class);

        // assert
        $this->expectException(BulkModelIsUndefined::class);

        // act
        $sut->update($model, []);
    }

    /**
     * @param array $events
     * @return void
     * @throws Exception
     * @dataProvider intersectEventsDataProvider
     */
    public function testIntersectEvents(array $events): void
    {
        // assert
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class);

        // act
        $sut->setEvents($events);

        // assert
        self::assertEmpty(
            array_filter(
                $sut->getEvents(),
                static fn (string $event): bool => !in_array($event, [
                    BulkEventEnum::SAVING,
                    BulkEventEnum::UPDATING,
                    BulkEventEnum::UPDATED,
                    BulkEventEnum::SAVED,
                ], true)
            )
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateUserCollectionFeature = $this->app->make(GenerateUserCollectionFeature::class);
    }

    protected function chunkCallbackDataProvider(): array
    {
        return [
            [PostgreSqlUser::class, 7, 3],
            [PostgreSqlUser::class, 12, 4],
        ];
    }

    protected function throwBulkModelIsUndefinedDataProvider(): array
    {
        return [
            'random string' => ['\Abcd'],
            'stdClass' => [stdClass::class],
            'class does not implement BulkModel' => [self::class],
        ];
    }

    protected function intersectEventsDataProvider(): array
    {
        return [
            'correct' => [
                [
                    BulkEventEnum::SAVING,
                    BulkEventEnum::UPDATING,
                    BulkEventEnum::UPDATED,
                    BulkEventEnum::SAVING,
                ],
            ],
            'extra' => [
                [
                    BulkEventEnum::SAVING,
                    BulkEventEnum::UPDATING,
                    BulkEventEnum::UPDATED,
                    BulkEventEnum::SAVING,
                    BulkEventEnum::UPDATING,
                ],
            ],
            'some' => [
                [
                    BulkEventEnum::UPDATING,
                    BulkEventEnum::UPDATED,
                ],
            ],
        ];
    }
}
