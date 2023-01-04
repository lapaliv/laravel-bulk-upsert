<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Insert;

use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

class SetEventsTest extends TestCase
{
    /**
     * @dataProvider data
     * @param string $event
     * @return void
     */
    public function test(string $event): void
    {
        [
            'sut' => $sut,
            'isSupport' => $isSupport,
        ] = $this->arrange($event);

        // act
        $sut->setEvents([$event]);

        // assert
        self::assertEquals(
            $isSupport,
            empty($sut->getEvents()) === false
        );
    }

    public function data(): array
    {
        $result = [];

        foreach (BulkEventEnum::ALL as $event) {
            $result[] = [$event];
        }

        return $result;
    }

    /**
     * @return array{
     *     isSupport: bool,
     *     sut: \Lapaliv\BulkUpsert\BulkInsert,
     * }
     */
    private function arrange(string $event): array
    {
        $supportEvents = [
            BulkEventEnum::CREATING,
            BulkEventEnum::CREATED,
            BulkEventEnum::SAVING,
            BulkEventEnum::SAVED,
        ];

        return [
            'isSupport' => in_array($event, $supportEvents, true),
            'sut' => new BulkInsert(MysqlUser::class),
        ];
    }
}