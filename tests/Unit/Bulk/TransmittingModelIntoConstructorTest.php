<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk;

use Illuminate\Support\Str;
use Lapaliv\BulkUpsert\Bulk;
use Lapaliv\BulkUpsert\Exceptions\BulkTransmittedClassIsNotAModel;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
final class TransmittingModelIntoConstructorTest extends TestCase
{
    public function testModel(): void
    {
        // arrange
        $payload = new User();

        // act
        $bulk = new Bulk($payload);

        // assert
        self::assertInstanceOf(Bulk::class, $bulk);
    }

    public function testModelClassName(): void
    {
        // arrange
        $payload = User::class;

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
        $this->expectException(BulkTransmittedClassIsNotAModel::class);

        // act
        new Bulk($payload);
    }

    public function testNotModel(): void
    {
        // arrange
        $modelName = 'M' . Str::random();
        eval('class ' . $modelName . ' {}');

        // assert
        $this->expectException(BulkTransmittedClassIsNotAModel::class);

        // act
        new Bulk('\\' . $modelName);
    }
}
