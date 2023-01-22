<?php

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Tests\Unit;

use Exception;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Exceptions\BulkModelIsUndefined;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;

final class BulkInsertUnitTest extends UnitTestCase
{
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
                static fn (string $event): bool => !in_array($event, [
                    BulkEventEnum::SAVING,
                    BulkEventEnum::CREATING,
                    BulkEventEnum::CREATED,
                    BulkEventEnum::SAVED,
                ], true)
            )
        );
    }

    /**
     * @return array<string, string>
     * @throws Exception
     */
    public function throwBulkModelIsUndefinedDataProvider(): array
    {
        return [
            'random string' => [base64_encode(random_bytes(3))],
            'class does not implement BulkModel' => [self::class],
        ];
    }

    /**
     * @return array[][]
     */
    public function intersectEventsDataProvider(): array
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
