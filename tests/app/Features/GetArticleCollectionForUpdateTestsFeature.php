<?php

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Carbon\Carbon;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Lapaliv\BulkUpsert\Tests\App\Collections\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\Model;

class GetArticleCollectionForUpdateTestsFeature
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    /**
     * @throws Exception
     */
    public function handle(string $model, int $count): ArticleCollection
    {
        /** @var Model|string $model */

        $result = new ArticleCollection();

        for ($i = 0; $i < $count; $i++) {
            /** @var Model $article */
            $article = new $model($this->generateData());
            $article->created_at = Carbon::parse($this->faker->dateTime());
            $article->updated_at = Carbon::parse($this->faker->dateTime());

            $article->save();

            // change some values
            $article->fill(
                $this->generateData()
            );

            $article->uuid = $article->getOriginal('uuid');

            $result->push($article);
        }

        return $result;
    }

    /**
     * @return array{
     *     uuid: string,
     *     name: string,
     *     content: string,
     *     is_new: boolean,
     *     date: boolean,
     *     microseconds: boolean,
     * }
     * @throws Exception
     */
    private function generateData(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'name' => $this->faker->name(),
            'content' => $this->faker->text(),
            'is_new' => $this->faker->boolean(),
            'date' => Carbon::parse($this->faker->dateTime)->toDateString(),
            'microseconds' => Carbon::now()
                ->subSeconds(
                    random_int(1, 999_999)
                )
                ->format('Y-m-d H:i:s.u'),
        ];
    }
}
