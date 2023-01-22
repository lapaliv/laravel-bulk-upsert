<?php

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Tests\Unit;

use Exception;
use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Exceptions\BulkModelIsUndefined;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;
use stdClass;

final class BulkUpdateUnitTest extends UnitTestCase
{
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

    /**
     * @return string[][]
     */
    public function throwBulkModelIsUndefinedDataProvider(): array
    {
        return [
            'random string' => ['\Abcd'],
            'stdClass' => [stdClass::class],
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
