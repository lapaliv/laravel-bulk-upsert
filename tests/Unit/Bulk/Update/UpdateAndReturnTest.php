<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Illuminate\Support\Arr;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class UpdateAndReturnTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param string $uniqBy
     *
     * @return void
     *
     * @dataProvider dataProvider
     */
    public function testDatabase(string $uniqBy): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOne(),
            $this->userGenerator->createOneAndDirty(),
        ]);
        $users = $users->keyBy('id');
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy([$uniqBy]);

        // act
        $result = $sut->updateAndReturn($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasUpdated($user)
        );
    }

    /**
     * @param string $uniqBy
     *
     * @return void
     *
     * @dataProvider dataProvider
     */
    public function testResult(string $uniqBy): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOne(),
            $this->userGenerator->createOneAndDirty(),
        ]);
        $users = $users->keyBy('id');
        $sut = MySqlUser::query()
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
     * @param string $uniqBy
     *
     * @return void
     *
     * @dataProvider dataProvider
     */
    public function testSelectColumns(string $uniqBy): void
    {
        // arrange
        $fields = ['id', 'email', 'name'];
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
        $sut = MySqlUser::query()
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
        return [
            'email' => ['email'],
            'id' => ['id'],
        ];
    }
}
