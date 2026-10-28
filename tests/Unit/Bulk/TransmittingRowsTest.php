<?php

namespace Tests\Unit\Bulk;

use Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\Post;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;

/**
 * The model instances which can be passed as bulk rows.
 *
 * @internal
 */
final class TransmittingRowsTest extends TestCaseWrapper
{
    /**
     * A bulk operation rejects a model of another class before building a query.
     *
     * @param string $method
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('methodsDataProvider')]
    public function testModelOfAnotherClassIsRejected(string $method): void
    {
        // arrange
        $post = Post::factory()->make();
        $sut = User::query()->bulk();

        // assert
        $this->expectException(BulkValueTypeIsNotSupported::class);

        // act
        $sut->{$method}([$post]);
    }

    /**
     * The operations which accept model instances as rows.
     *
     * @return array<string, array{string}>
     */
    public static function methodsDataProvider(): array
    {
        return [
            'create' => ['create'],
            'update' => ['update'],
            'upsert' => ['upsert'],
            'delete' => ['delete'],
            'force delete' => ['forceDelete'],
        ];
    }
}
