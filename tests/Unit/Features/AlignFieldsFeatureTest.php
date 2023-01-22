<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Illuminate\Database\Query\Expression;
use Lapaliv\BulkUpsert\Features\AlignFieldsFeature;
use Lapaliv\BulkUpsert\Tests\TestCase;

final class AlignFieldsFeatureTest extends TestCase
{
    /**
     * @param array $data
     * @param array $fields
     * @param mixed $default
     * @return void
     * @dataProvider dataProvider
     */
    public function test(array $data, array $fields, ?Expression $default): void
    {
        // arrange
        /** @var AlignFieldsFeature $sut */
        $sut = $this->app->make(AlignFieldsFeature::class);

        // act
        $result = $sut->handle($data, $default);

        // arrange
        foreach ($data as $index => $item) {
            foreach ($fields as $field) {
                self::assertArrayHasKey($field, $result[$index]);

                self::assertEquals(
                    $item[$field] ?? $default,
                    $result[$index][$field]
                );
            }
        }
    }

    public function dataProvider(): array
    {
        return [
            [
                [
                    [
                        'email' => 'mariposa1209@ggmal.ml',
                    ],
                    [
                        'name' => 'Ester J. Heaton',
                    ],
                    [
                        'email' => 'radmirkar@valibri.com',
                        'name' => 'Alice D. Simpson',
                        'phone' => '202-555-0168',
                    ],
                ],
                [
                    'email',
                    'name',
                    'phone',
                ],
                'default' => null,
            ],
            [
                [
                    [
                        'email' => 'ossj43@email-temp.com',
                        'phone' => '+1-202-555-0180',
                    ],
                    [
                        'name' => 'Saul J. Stewart',
                    ],
                    [
                        'email' => 'chief85@goldinbox.net',
                        'name' => 'Mary E. Valdez',
                    ],
                ],
                [
                    'email',
                    'name',
                    'phone',
                ],
                'default' => new Expression('default'),
            ],
        ];
    }
}
