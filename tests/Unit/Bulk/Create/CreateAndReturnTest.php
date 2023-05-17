<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlStory;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\Story;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class CreateAndReturnTest extends TestCase
{
    use UserTestTrait;

    public function testDatabase(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->createAndReturn($users);

        // assert
        foreach ($users as $user) {
            $this->assertDatabaseHas($user->getTable(), [
                'email' => $user->email,
                'name' => $user->name,
                'gender' => $user->gender->value,
                'avatar' => $user->avatar,
                'posts_count' => $user->posts_count,
                'is_admin' => $user->is_admin,
                'balance' => $user->balance,
                'birthday' => $user->birthday,
                'phones' => $this->phonesToCast($user),
                'last_visited_at' => $user->last_visited_at,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
                'deleted_at' => $user->deleted_at?->toDateTimeString(),
            ], $user->getConnectionName());
        }
    }

    public function testResult(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        /** @var UserCollection $result */
        $result = $sut->createAndReturn($users);

        // assert
        foreach ($users as $index => $user) {
            self::assertEquals($user->email, $result->get($index)->email);
            self::assertEquals($user->gender->value, $result->get($index)->gender->value);
            self::assertEquals($user->avatar, $result->get($index)->avatar);
            self::assertEquals($user->posts_count, $result->get($index)->posts_count);
            self::assertEquals($user->is_admin, $result->get($index)->is_admin);
            self::assertEquals($user->balance, $result->get($index)->balance);
            self::assertEquals($user->birthday, $result->get($index)->birthday);
            self::assertEquals($user->phones, $result->get($index)->phones);
            self::assertEquals($user->last_visited_at, $result->get($index)->last_visited_at);
            self::assertEquals(Carbon::now()->toDateTimeString(), $result->get($index)->created_at->toDateTimeString());
            self::assertNull($result->get($index)->deleted_at);
            self::assertTrue($result->get($index)->wasRecentlyCreated);
        }
    }

    public function testSelectColumns(): void
    {
        // arrange
        $users = [
            Arr::only(
                $this->userGenerator->makeOne()->toArray(),
                ['email', 'name', 'gender', 'posts_count', 'is_admin']
            ),
            Arr::only(
                $this->userGenerator->makeOne()->toArray(),
                ['email', 'name', 'gender', 'posts_count', 'is_admin']
            ),
        ];
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        /** @var UserCollection $result */
        $result = $sut->createAndReturn($users, ['id']);

        // assert
        self::assertInstanceOf(UserCollection::class, $result);

        foreach ($result as $user) {
            foreach ($user->getAttributes() as $key => $value) {
                if ($key === 'id' || $key === 'email') {
                    continue;
                }

                $this->fail('The model has extra attributes');
            }
        }
    }

    public function testWithoutIncrementing(): void
    {
        // arrange
        $stories = MySqlStory::factory()
            ->count(10)
            ->make();
        $sut = MySqlStory::query()
            ->bulk()
            ->uniqueBy(['uuid']);

        // act
        $result = $sut->createAndReturn($stories);

        // assert
        self::assertCount($stories->count(), $result);

        $stories->each(
            function (Story $story) use ($result): void {
                /** @var Story $actualStory */
                $actualStory = $result->where('uuid', $story->uuid)
                    ->first();

                self::assertNotNull($actualStory);
                self::assertEquals($story->title, $actualStory->title);
                self::assertEquals($story->content, $actualStory->content);
            }
        );
    }
}
