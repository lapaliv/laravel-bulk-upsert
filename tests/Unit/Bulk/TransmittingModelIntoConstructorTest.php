<?php

namespace Tests\Unit\Bulk;

use Illuminate\Support\Str;
use Lapaliv\BulkUpsert\Bulk;
use Lapaliv\BulkUpsert\Exceptions\BulkTransmittedClassIsNotAModel;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;

/**
 * The values which the constructor of {@see \Lapaliv\BulkUpsert\Bulk} accepts as the model.
 *
 * @internal
 */
final class TransmittingModelIntoConstructorTest extends TestCaseWrapper
{
    /**
     * A model instance is accepted and gives a builder back.
     *
     * @return void
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testModel(): void
    {
        // arrange
        $payload = new User();

        // act
        $bulk = new Bulk($payload);

        // assert
        self::assertInstanceOf(Bulk::class, $bulk);
    }

    /**
     * The class name of a model is accepted and gives a builder back.
     *
     * @return void
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testModelClassName(): void
    {
        // arrange
        $payload = User::class;

        // act
        $bulk = new Bulk($payload);

        // assert
        self::assertInstanceOf(Bulk::class, $bulk);
    }

    /**
     * A random string which is not a class name throws.
     *
     * @return void
     */
    public function testRandomString(): void
    {
        // arrange
        $payload = Str::random();

        // assert
        $this->expectException(BulkTransmittedClassIsNotAModel::class);

        // act
        new Bulk($payload);
    }

    /**
     * The name of a class which is not a model throws.
     *
     * @return void
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
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
