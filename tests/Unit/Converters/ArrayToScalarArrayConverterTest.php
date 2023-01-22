<?php

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Tests\Unit\Converters;

use Carbon\Carbon;
use Faker\Factory;
use Faker\Generator;
use JsonException;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Exceptions\BulkAttributeTypeIsNotScalar;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;
use stdClass;

final class ArrayToScalarArrayConverterTest extends UnitTestCase
{
    private Generator $faker;

    /**
     * @return void
     * @throws JsonException
     */
    public function testScalarsWithoutDates(): void
    {
        // assert
        /** @var AttributesToScalarArrayConverter $sut */
        $sut = $this->app->make(AttributesToScalarArrayConverter::class);
        $attributes = [
            'int' => $this->faker->numberBetween(),
            'string' => $this->faker->uuid(),
            'boolean' => $this->faker->boolean(),
            'float' => $this->faker->randomFloat(),
            'null' => null,
        ];

        // act
        $result = $sut->handle([], $attributes);

        // assert
        foreach ($attributes as $key => $value) {
            self::assertArrayHasKey($key, $result);
            self::assertEquals($value, $result[$key]);
        }
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testStdClass(): void
    {
        // assert
        /** @var AttributesToScalarArrayConverter $sut */
        $sut = $this->app->make(AttributesToScalarArrayConverter::class);
        $attributes = [
            'stdClass' => new stdClass(),
            'null' => null,
        ];

        // assert
        $this->expectException(BulkAttributeTypeIsNotScalar::class);

        // act
        $sut->handle([], $attributes);
    }

    /**
     * @dataProvider datesDataProvider
     * @param string $dateFormat
     * @return void
     * @throws JsonException
     */
    public function testDate(string $dateFormat): void
    {
        // assert
        /** @var AttributesToScalarArrayConverter $sut */
        $sut = $this->app->make(AttributesToScalarArrayConverter::class);
        $attributes = [
            'key' => Carbon::parse($this->faker->dateTime()),
        ];

        // act
        $result = $sut->handle(['key' => $dateFormat], $attributes);

        // assert
        foreach ($attributes as $key => $value) {
            self::assertIsString($result[$key]);
            self::assertEquals(
                $value->format($dateFormat),
                $result[$key],
            );
        }
    }

    /**
     * @return string[][]
     */
    public function datesDataProvider(): array
    {
        return [
            'date' => ['Y-m-d'],
            'timestamp' => ['Y-m-d H:i:s'],
            'milliseconds' => ['Y-m-d H:i:s.v'],
            'microseconds' => ['Y-m-d H:i:s.u'],
            'custom' => ['d.m.y s:i:h (u)'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }
}
