<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use JsonException;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
class UpdateAndReturnTest extends TestCase
{
    use UpdateTestTrait;

    /**
     * @param string $uniqBy
     *
     * @return void
     *
     * @throws JsonException
     *
     * @dataProvider dataProvider
     */
    public function test(string $uniqBy): void
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
        self::assertInstanceOf(UserCollection::class, $result);
        self::assertCount($users->count(), $result);

        foreach ($result as $user) {
            self::assertArrayHasKey($user->id, $users);
            $users->forget($user->id);
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
