<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Update;

use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

class NoDataTest extends TestCase
{
    /**
     * @return void
     */
    public function test(): void
    {
        ['sut' => $sut] = $this->arrange();

        // act
        $sut->update(MySqlArticle::class, [], []);

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
                ->chunk(100, fn () => $this->fail())
                ->onUpdating(fn () => $this->fail())
                ->onUpdated(fn () => $this->fail())
        ];
    }
}
