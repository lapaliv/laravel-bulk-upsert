<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lapaliv\BulkUpsert\Bulk;
use Lapaliv\BulkUpsert\Exceptions\ModelHasToImplementBulkModelInterface;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
final class TransmittingModelIntoConstructorTest extends TestCase
{
    public function testBulkModel(): void
    {
        // arrange
        $payload = new MySqlUser();

        // act
        $bulk = new Bulk($payload);

        // assert
        self::assertInstanceOf(Bulk::class, $bulk);
    }

    public function testBulkModelClassName(): void
    {
        // arrange
        $payload = MySqlUser::class;

        // act
        $bulk = new Bulk($payload);

        // assert
        self::assertInstanceOf(Bulk::class, $bulk);
    }

    public function testRandomString(): void
    {
        // arrange
        $payload = Str::random();

        // assert
        $this->expectException(ModelHasToImplementBulkModelInterface::class);

        // act
        new Bulk($payload);
    }

    public function testNotBulkModel(): void
    {
        // arrange
        $modelName = 'M' . Str::random();
        eval('class ' . $modelName . ' extends ' . Model::class . '{}');

        // assert
        $this->expectException(ModelHasToImplementBulkModelInterface::class);

        // act
        new Bulk('\\' . $modelName);
    }
}
