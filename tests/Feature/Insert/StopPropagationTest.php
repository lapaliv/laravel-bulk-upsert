<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Insert;

use Illuminate\Events\Dispatcher;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\Model;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

final class StopPropagationTest extends TestCase
{
    private const NUMBER_OF_USERS = 5;

    /**
     * @dataProvider data
     * @param string $model
     * @param array<string, boolean> $dispatchedEvents
     * @param string[] $notDispatchedEvents
     * @return void
     */
    public function test(
        string $model,
        array  $dispatchedEvents,
        array  $notDispatchedEvents
    ): void {
        [
            'collection' => $collection,
            'sut' => $sut,
        ] = $this->arrange($model, $dispatchedEvents, $notDispatchedEvents);

        // act
        $sut->insert($model, ['email'], $collection);

        // assert
        // This part is described in the `arrange` method
    }

    public function data(): array
    {
        $models = [
            MySqlUser::class,
            PostgreSqlUser::class,
        ];

        $result = [];

        foreach ($models as $model) {
            $result[] = [
                $model,
                [
                    BulkEventEnum::SAVING => false,
                ],
                [
                    BulkEventEnum::CREATING,
                    BulkEventEnum::CREATED,
                    BulkEventEnum::SAVED,
                ],
            ];

            $result[] = [
                $model,
                [
                    BulkEventEnum::SAVING => true,
                    BulkEventEnum::CREATING => false,
                ],
                [
                    BulkEventEnum::CREATED,
                    BulkEventEnum::SAVED,
                ],
            ];

            $result[] = [
                $model,
                [
                    BulkEventEnum::SAVING => true,
                    BulkEventEnum::CREATING => true,
                    BulkEventEnum::CREATED => false,
                    BulkEventEnum::SAVED => true,
                ],
                [],
            ];

            $result[] = [
                $model,
                [
                    BulkEventEnum::SAVING => true,
                    BulkEventEnum::CREATING => true,
                    BulkEventEnum::CREATED => true,
                    BulkEventEnum::SAVED => false,
                ],
                [],
            ];

            $result[] = [
                $model,
                [
                    BulkEventEnum::SAVING => true,
                    BulkEventEnum::CREATING => true,
                    BulkEventEnum::CREATED => true,
                    BulkEventEnum::SAVED => true,
                ],
                [],
            ];
        }

        return $result;
    }

    /**
     * @param string $model
     * @param array<string, boolean> $dispatchedEvents
     * @param array $notDispatchedEvents
     * @return array{
     *     collection: \Illuminate\Database\Eloquent\Collection,
     *     sut: \Lapaliv\BulkUpsert\BulkInsert,
     *     generateEventNameFeature: \Lapaliv\BulkUpsert\Tests\App\Features\GenerateEloquentEventNameFeature,
     * }
     */
    protected function arrange(string $model, array $dispatchedEvents, array $notDispatchedEvents): array
    {
        Model::setEventDispatcher(
            $this->app->make(Dispatcher::class)
        );

        // Can't use Event::fake() because it misses the return value
        foreach ($dispatchedEvents as $event => $return) {
            /** @var \Lapaliv\BulkUpsert\Tests\App\Models\Model $model */
            $model::registerModelEvent($event, static function () use ($return) {
                self::assertTrue(true);
                return $return;
            });
        }

        foreach ($notDispatchedEvents as $event) {
            $model::registerModelEvent($event, function () use ($event) {
                $this->fail();
            });
        }

        $generateUserCollectionFeature = new GenerateUserCollectionFeature($model);

        return [
            'collection' => $generateUserCollectionFeature->handle(
                self::NUMBER_OF_USERS,
            ),
            'sut' => $this->app->make(BulkInsert::class),
        ];
    }
}
