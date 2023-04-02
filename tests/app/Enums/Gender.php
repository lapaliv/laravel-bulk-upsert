<?php

namespace Lapaliv\BulkUpsert\Tests\App\Enums;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * @internal
 */
class Gender implements CastsAttributes
{
    private const MALE = 'male';
    private const FEMALE = 'female';

    public function __construct(public ?string $value = null)
    {
        //
    }

    public static function male(): static
    {
        return new self(self::MALE);
    }

    public static function female(): static
    {
        return new self(self::FEMALE);
    }

    public function get($model, string $key, $value, array $attributes): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return match ($value) {
            self::MALE => new self(self::MALE),
            self::FEMALE => new self(self::FEMALE),
        };
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof self) {
            return $value->value;
        }

        return $value;
    }
}
