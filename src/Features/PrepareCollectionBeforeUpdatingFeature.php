<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Exceptions\BulkModelIsUndefined;

class PrepareCollectionBeforeUpdatingFeature
{
    public function __construct(
        private KeyByFeature $keyByFeature,
        private GetDirtyAttributesFeature $getDirtyAttributesFeature,
    )
    {
        //
    }

    /**
     * @param BulkModel $eloquent
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param Collection $actual
     * @param Collection $expected
     * @return Collection<BulkModel>
     */
    public function handle(
        BulkModel $eloquent,
        array $uniqueAttributes,
        ?array $updateAttributes,
        Collection $actual,
        Collection $expected,
    ): Collection
    {
        $keyedActual = $this->keyByFeature->handle($actual, $uniqueAttributes);
        $keyedExpected = $this->keyByFeature->handle($expected, $uniqueAttributes);
        $result = $eloquent->newCollection();

        /** @var BulkModel $actualModel */
        foreach ($keyedActual as $key => $actualModel) {
            if (array_key_exists($key, $keyedExpected) === false) {
                throw new BulkModelIsUndefined();
            }

            /** @var BulkModel $expectedModel */
            $expectedModel = $keyedExpected[$key];

            if (empty($updateAttributes)) {
                // Saving the correct format of the attributeValue.
                // For example, if the attributeValue is a json string
                // then it will be  a string with string (""[..]"") not a string with json ("[]")
                $dirtyAttributes = $this->getDirtyAttributesFeature->handle($expectedModel);
                foreach ($dirtyAttributes as $attributeName => $attributeValue) {
                    $actualModel->setAttribute(
                        $attributeName,
                        $expectedModel->getAttribute($attributeName),
                    );
                }
            } else {
                foreach ($updateAttributes as $attribute) {
                    $actualModel->setAttribute(
                        $attribute,
                        $expectedModel->getAttribute($attribute),
                    );
                }
            }

            $result->push($actualModel);
        }

        return $result;
    }
}
