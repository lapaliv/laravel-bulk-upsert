<?php

namespace Tests\App\Traits;

/**
 * @internal
 */
trait GlobalTouches
{
    private static ?array $globalTouches = null;

    public function getTouchedRelations(): array
    {
        if (static::$globalTouches === null) {
            return parent::getTouchedRelations();
        }

        return static::$globalTouches;
    }

    public static function setGlobalTouchedRelations(?array $relations): void
    {
        static::$globalTouches = $relations;
    }
}
