<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Insert;

use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

class NoDataTest extends TestCase
{
    public function test(): void
    {
        ['sut' => $sut] = $this->arrange();

        // act
        $sut->insert(MySqlUser::class, [], []);

        // assert
        self::assertTrue(true);
    }

    /**
     * @return array{
     *     sut: BulkInsert,
     * }
     */
    private function arrange(): array
    {
        return [
            'sut' => $this->app->make(BulkInsert::class)
                ->chunk(100, fn () => $this->fail())
                ->onInserting(fn () => $this->fail())
                ->onInserted(fn () => $this->fail())
        ];
    }
}
