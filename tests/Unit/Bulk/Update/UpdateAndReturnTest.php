<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Illuminate\Support\Arr;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpdateAndReturnTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     * @param string $uniqBy
     *
     * @return void
     *
     * @dataProvider dataProvider
     */
    public function testDatabase(string $model, string $uniqBy): void
    {
        // arrange
        $this->userGenerator->setModel($model);
        $users = new UserCollection([
            $this->userGenerator->createOne(),
            $this->userGenerator->createOneAndDirty(),
        ]);
        $users = $users->keyBy('id');
        $sut = $model::query()
            ->bulk()
            ->uniqueBy([$uniqBy]);

        // act
        $sut->updateAndReturn($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasUpdated($user)
        );
    }

    /**
     * @param class-string<User> $model
     * @param string $uniqBy
     *
     * @return void
     *
     * @dataProvider dataProvider
     */
    public function testResult(string $model, string $uniqBy): void
    {
        // arrange
        $this->userGenerator->setModel($model);
        $users = new UserCollection([
            $this->userGenerator->createOne(),
            $this->userGenerator->createOneAndDirty(),
        ]);
        $users = $users->keyBy('id');
        $sut = $model::query()
            ->bulk()
            ->uniqueBy([$uniqBy]);

        // act
        $result = $sut->updateAndReturn($users);

        // assert
        self::assertInstanceOf(UserCollection::class, $result);
        self::assertCount($users->count(), $result);

        foreach ($result as $user) {
            self::assertArrayHasKey($user->id, $users);
            $users->forget($user->id);
        }
    }

    /**
     * @param class-string<User> $model
     * @param string $uniqBy
     *
     * @return void
     *
     * @dataProvider dataProvider
     */
    public function testSelectColumns(string $model, string $uniqBy): void
    {
        // arrange
        $fields = ['id', 'email', 'name'];
        $this->userGenerator->setModel($model);
        $users = new UserCollection([
            Arr::only(
                $this->userGenerator->createOne()->toArray(),
                $fields,
            ),
            Arr::only(
                $this->userGenerator->createOneAndDirty()->toArray(),
                $fields,
            ),
        ]);
        $users = $users->keyBy('id');
        $sut = $model::query()
            ->bulk()
            ->uniqueBy([$uniqBy]);

        // act
        $result = $sut->updateAndReturn($users->toArray(), ['id', 'email', 'name']);

        // assert
        self::assertInstanceOf(UserCollection::class, $result);
        self::assertCount($users->count(), $result);

        foreach ($result as $user) {
            foreach ($user->getAttributes() as $key => $value) {
                if (in_array($key, $fields, true)) {
                    continue;
                }

                if ($key === 'updated_at') {
                    continue;
                }

                self::fail('The model has extra attributes');
            }
        }
    }

    public function dataProvider(): array
    {
        $target = [
            'email' => ['email'],
            'id' => ['id'],
        ];

        $result = [];

        foreach ($this->userModels() as $type => $model) {
            foreach ($target as $key => $value) {
                $result[$key . ' && ' . $type] = [
                    $model,
                    ...$value,
                ];
            }
        }

        return $result;
    }

    public function userModels(): array
    {
        return [
            'mysql' => MySqlUser::class,
            'postgre' => PostgreSqlUser::class,
        ];
    }
}
