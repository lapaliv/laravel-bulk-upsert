<?php

namespace Lapaliv\BulkUpsert\Tests\Unit;

use Exception;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Exceptions\BulkModelIsUndefined;
use Lapaliv\BulkUpsert\Tests\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\Features\SwitchDriverToNullDriverFeature;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\Support\Callback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Mockery;
use Mockery\VerificationDirector;

class InsertTest extends TestCase
{
    private GenerateUserCollectionFeature $generateUserCollectionFeature;
    private SwitchDriverToNullDriverFeature $switchDriverToNullDriverFeature;

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
        $this->switchDriverToNullDriverFeature->handle();

        /** @var BulkInsert $sut */
        $sut = $this->app
            ->make(BulkInsert::class)
            ->chunk($chunkSize, $callbackSpy);

        // act
        $sut->insert($model, ['email'], $users);

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
        /** @var BulkInsert $sut */
        $sut = $this->app->make(BulkInsert::class);

        // assert
        $this->expectException(BulkModelIsUndefined::class);

        // act
        $sut->insert($model, [], []);
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
        /** @var BulkInsert $sut */
        $sut = $this->app->make(BulkInsert::class);

        // act
        $sut->setEvents($events);

        // assert
        self::assertEmpty(
            array_filter(
                $sut->getEvents(),
                static fn(string $event): bool => !in_array($event, [
                    BulkEventEnum::SAVING,
                    BulkEventEnum::CREATING,
                    BulkEventEnum::CREATED,
                    BulkEventEnum::SAVED,
                ], true)
            )
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
        $this->generateUserCollectionFeature = $this->app->make(GenerateUserCollectionFeature::class);
        $this->switchDriverToNullDriverFeature = $this->app->make(SwitchDriverToNullDriverFeature::class);
    }

    protected function chunkCallbackDataProvider(): array
    {
        return [
            [MysqlUser::class, 5, 1],
            [MysqlUser::class, 10, 3],
        ];
    }

    protected function throwBulkModelIsUndefinedDataProvider(): array
    {
        return [
            'random string' => [base64_encode(random_bytes(3))],
            'class does not implement BulkModel' => [self::class],
        ];
    }

    protected function intersectEventsDataProvider(): array
    {
        return [
            'correct' => [
                [
                    BulkEventEnum::SAVING,
                    BulkEventEnum::CREATING,
                    BulkEventEnum::CREATED,
                    BulkEventEnum::SAVING,
                ],
            ],
            'extra' => [
                [
                    BulkEventEnum::SAVING,
                    BulkEventEnum::CREATING,
                    BulkEventEnum::CREATED,
                    BulkEventEnum::SAVING,
                    BulkEventEnum::UPDATING,
                ],
            ],
            'some' => [
                [
                    BulkEventEnum::CREATING,
                    BulkEventEnum::CREATED,
                ],
            ],
        ];
    }
}
