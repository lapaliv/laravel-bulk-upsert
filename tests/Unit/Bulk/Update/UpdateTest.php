<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpdateTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param array|callable|string $uniqBy
     *
     * @return void
     *
     * @dataProvider dataProvider
     */
    public function test(array|string|callable $uniqBy): void
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
            '[email]' => [['email']],
            '[[email]]' => [[['email']]],
            '() => email' => [fn () => 'email'],
            'id' => ['id'],
            '[id]' => [['id']],
            '[[id]]' => [['id']],
            '() => id' => [fn () => 'id'],
        ];
    }
}
