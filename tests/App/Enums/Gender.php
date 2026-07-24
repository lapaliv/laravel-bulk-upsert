<?php

namespace Tests\App\Enums;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use UnexpectedValueException;

/**
 * The gender of a test user and its Eloquent cast at the same time.
 *
 * The class wraps a plain string and is registered as the cast of the `gender` column
 * of the User model. The tests use it to check that the package writes and reads an
 * attribute which holds an object instead of a scalar value.
 *
 * @internal
 */
final class Gender implements CastsAttributes
{
    /**
     * The value which the male gender has in the database.
     *
     * @var string
     */
    private const MALE = 'male';

    /**
     * The value which the female gender has in the database.
     *
     * @var string
     */
    private const FEMALE = 'female';

    /**
     * Create a new gender instance.
     *
     * @param string|null $value
     *
     * @return void
     */
    public function __construct(public ?string $value = null)
    {
        //
    }

    /**
     * Create the male gender.
     *
     * @return self
     */
    public static function male(): self
    {
        return new self(self::MALE);
    }

    /**
     * Create the female gender.
     *
     * @return self
     */
    public static function female(): self
    {
        return new self(self::FEMALE);
    }

    /**
     * Cast the value which was read from the database.
     *
     * A value which is already a gender is given back as it is.
     *
     * @param mixed $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     *
     * @return self
     *
     * @throws \UnexpectedValueException
     */
    public function get($model, string $key, $value, array $attributes): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return match ($value) {
            self::MALE => new self(self::MALE),
            self::FEMALE => new self(self::FEMALE),
            default => throw new UnexpectedValueException('Value ' . $value . ' is undefined')
        };
    }

    /**
     * Prepare the value which will be stored in the database.
     *
     * A gender becomes its string value, every other value is given back as it is.
     *
     * @param mixed $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     *
     * @return mixed
     */
    public function set($model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value instanceof self) {
            return $value->value;
        }

        return $value;
    }
}
