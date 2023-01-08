<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Update;

use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Tests\Models\MysqlArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

class NoDataTest extends TestCase
{
    /**
     * @return void
     * @group 123
     */
    public function test(): void
    {
        ['sut' => $sut] = $this->arrange();

        // act
        $sut->update(MysqlArticle::class, [], []);

        // assert
        self::assertTrue(true);
    }

    /**
     * @return array{
     *     sut: BulkUpdate,
     * }
     */
    private function arrange(): array
    {
        return [
            'sut' => $this->app->make(BulkUpdate::class)
                ->chunk(100, fn() => $this->fail())
                ->onUpdating(fn() => $this->fail())
                ->onUpdated(fn() => $this->fail())
        ];
    }
}
