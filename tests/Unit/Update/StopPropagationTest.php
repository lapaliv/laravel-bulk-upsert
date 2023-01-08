<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Update;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Events\Dispatcher;
use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\Features\GetUserCollectionForUpdateTestsFeature;
use Lapaliv\BulkUpsert\Tests\Models\Model;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\Models\PostgresUser;
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
    ): void
    {
        [
            'collection' => $collection,
            'sut' => $sut,
        ] = $this->arrange($model, $dispatchedEvents, $notDispatchedEvents);

        // act
        $sut->update($model, $collection, ['email']);

        // assert
        // This part is described in the `arrange` method
    }

    public function data(): array
    {
        $models = [
            MysqlUser::class,
            PostgresUser::class,
        ];

        $result = [];

        foreach ($models as $model) {
            $result[] = [
                $model,
                [
                    BulkEventEnum::SAVING => false,
                ],
                [
                    BulkEventEnum::UPDATING,
                    BulkEventEnum::UPDATED,
                    BulkEventEnum::SAVED,
                ],
            ];

            $result[] = [
                $model,
                [
                    BulkEventEnum::SAVING => true,
                    BulkEventEnum::UPDATING => false,
                ],
                [
                    BulkEventEnum::UPDATED,
                    BulkEventEnum::SAVED,
                ],
            ];

            $result[] = [
                $model,
                [
                    BulkEventEnum::SAVING => true,
                    BulkEventEnum::UPDATING => true,
                    BulkEventEnum::UPDATED => false,
                    BulkEventEnum::SAVED => true,
                ],
                [],
            ];

            $result[] = [
                $model,
                [
                    BulkEventEnum::SAVING => true,
                    BulkEventEnum::UPDATING => true,
                    BulkEventEnum::UPDATED => true,
                    BulkEventEnum::SAVED => false,
                ],
                [],
            ];

            $result[] = [
                $model,
                [
                    BulkEventEnum::SAVING => true,
                    BulkEventEnum::UPDATING => true,
                    BulkEventEnum::UPDATED => true,
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
     *     collection: Collection,
     *     sut: BulkUpdate,
     * }
     */
    protected function arrange(string $model, array $dispatchedEvents, array $notDispatchedEvents): array
    {
        /** @var GetUserCollectionForUpdateTestsFeature $generateUserCollectionFeature */
        $generateUserCollectionFeature = $this->app->make(GetUserCollectionForUpdateTestsFeature::class);
        $collection = $generateUserCollectionFeature->handle(
            $model,
            self::NUMBER_OF_USERS,
        );

        Model::setEventDispatcher(
            $this->app->make(Dispatcher::class)
        );

        // Can't use Event::fake() because it misses the return value
        foreach ($dispatchedEvents as $event => $return) {
            /** @var Model $model */
            $model::registerModelEvent($event, static function () use ($return): bool {
                self::assertTrue(true);
                return $return;
            });
        }

        foreach ($notDispatchedEvents as $event) {
            $model::registerModelEvent($event, function (): void {
                $this->fail();
            });
        }

        return [
            'collection' => $collection,
            'sut' => $this->app->make(BulkUpdate::class),
        ];
    }
}
