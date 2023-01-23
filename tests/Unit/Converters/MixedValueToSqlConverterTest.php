<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Converters;

use Illuminate\Database\Query\Expression;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;

final class MixedValueToSqlConverterTest extends UnitTestCase
{
    /**
     * @dataProvider dataProvider
     * @param mixed $value
     * @param string $expectedResult
     * @param array $expectedBindings
     * @return void
     */
    public function test(mixed $value, string $expectedResult, array $expectedBindings): void
    {
        // arrange
        /** @var MixedValueToSqlConverter $sut */
        $sut = $this->app->make(MixedValueToSqlConverter::class);
        $actualBindings = [];

        // act
        $actualResult = $sut->handle($value, $actualBindings);

        // assert
        self::assertEquals($expectedResult, $actualResult);
        self::assertEquals($expectedBindings, $actualBindings);
    }

    public function dataProvider(): array
    {
        return [
            'integer' => [1, '1', []],
            'string' => ['John', '?', ['John']],
            'true' => [true, 'true', []],
            'false' => [false, 'false', []],
            'float' => [1.23, '1.23', []],
            'null' => [null, 'null', []],
            'Expression(string)' => [new Expression('raw'), 'raw', []],
            'Expression(int)' => [new Expression(10), '10', []],
            'Expression(float)' => [new Expression(1.234), '1.234', []],
            'Expression(true)' => [new Expression(true), 'true', []],
            'Expression(false)' => [new Expression(false), 'false', []],
            'Expression(null)' => [new Expression(null), 'null', []],
            'BuilderRawExpression' => [new BuilderRawExpression('raw'), 'raw', []],
        ];
    }
}
