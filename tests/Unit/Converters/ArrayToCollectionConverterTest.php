<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Converters;

use Exception;
use Lapaliv\BulkUpsert\Converters\ArrayToCollectionConverter;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

class ArrayToCollectionConverterTest extends TestCase
{
    private GenerateUserCollectionFeature $generateUserCollectionFeature;

    /**
     * @param string $model
     * @return void
     * @throws Exception
     * @dataProvider dataProvider
     */
    public function test(string $model): void
    {
        // arrange
        /** @var ArrayToCollectionConverter $sut */
        $sut = $this->app->make(ArrayToCollectionConverter::class);
        $columns = ['email', 'name', 'phone', 'date', 'microseconds'];
        /** @var User $eloquent */
        $eloquent = new $model();
        $rows = [
            ...$this->generateUserCollectionFeature->handle($model, 1, $columns),
            ...$this->generateUserCollectionFeature->handle($model, 1, $columns)->toArray(),
        ];

        // act
        $result = $sut->handle($eloquent, $rows);

        // assert
        self::assertInstanceOf(get_class($eloquent->newCollection()), $result);
        $result->each(
            static function (mixed $item) use ($model): void {
                self::assertInstanceOf($model, $item);
            }
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateUserCollectionFeature = $this->app->make(GenerateUserCollectionFeature::class);
    }

    protected function dataProvider(): array
    {
        return [
            [MySqlUser::class],
            [PostgreSqlUser::class],
        ];
    }
}
