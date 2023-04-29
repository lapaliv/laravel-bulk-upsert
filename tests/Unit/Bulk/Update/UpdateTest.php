<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use JsonException;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
class UpdateTest extends TestCase
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
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy($uniqBy);

        // act
        $sut->update($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasUpdated($user)
        );
    }

    public function dataProvider(): array
    {
        return [
            'email' => ['email'],
            'id' => ['id'],
        ];
    }
}
