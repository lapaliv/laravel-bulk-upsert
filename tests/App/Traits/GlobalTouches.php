<?php

namespace Tests\App\Traits;

/**
 * Lets a test set the touched relations of a whole model class.
 *
 * Eloquent reads the touched relations from the `$touches` property of the model. The
 * trait keeps a static list instead, so a test can change it for every instance of the
 * class and reset it afterwards.
 *
 * @internal
 */
trait GlobalTouches
{
    /**
     * The relations which every model of the class touches.
     *
     * When it is `null`, the model falls back to the list of the parent class.
     *
     * @var array|null
     */
    private static ?array $globalTouches = null;

    /**
     * Get the relationships that will be touched.
     *
     * @return array
     */
    public function getTouchedRelations(): array
    {
        if (static::$globalTouches === null) {
            return parent::getTouchedRelations();
        }

        return static::$globalTouches;
    }

    /**
     * Set the relations which every model of the class touches.
     *
     * Pass `null` to give the list of the parent class back.
     *
     * @param array|null $relations
     *
     * @return void
     */
    public static function setGlobalTouchedRelations(?array $relations): void
    {
        static::$globalTouches = $relations;
    }
}
