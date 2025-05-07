<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Scenarios;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Scenarios\CreateScenario;
use Lapaliv\BulkUpsert\Tests\TestCaseWrapper;

abstract class CreateScenarioTestCase extends TestCaseWrapper
{
    protected function handleCreateScenario(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        bool $ignore = false,
        array $dateFields = [],
        array $selectColumns = ['*'],
        ?string $deletedAtColumn = null,
    ): void
    {
        // arrange
        $sut = $this->getFromContainer(CreateScenario::class);

        // act
        $sut->handle(
            $data,
            $eventDispatcher,
            ignore: $ignore,
            dateFields: $dateFields,
            selectColumns: $selectColumns,
            deletedAtColumn: $deletedAtColumn,
        );
    }
}
