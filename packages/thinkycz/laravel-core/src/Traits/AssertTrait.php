<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Traits;

use BackedEnum;
use Brick\Math\BigDecimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Panicker;

trait AssertTrait
{
    /**
     * Assert string.
     */
    public function assertString(string $key): string
    {
        $value = $this->mixed($key);

        \assert(\is_string($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert nullable string.
     */
    public function assertNullableString(string $key): string|null
    {
        $value = $this->mixed($key);

        \assert($value === null || \is_string($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert bool.
     */
    public function assertBool(string $key): bool
    {
        $value = $this->mixed($key);

        \assert(\is_bool($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert nullable bool.
     */
    public function assertNullableBool(string $key): bool|null
    {
        $value = $this->mixed($key);

        \assert($value === null || \is_bool($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert int.
     */
    public function assertInt(string $key): int
    {
        $value = $this->mixed($key);

        \assert(\is_int($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert nullable int.
     */
    public function assertNullableInt(string $key): int|null
    {
        $value = $this->mixed($key);

        \assert($value === null || \is_int($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert float.
     */
    public function assertFloat(string $key): float
    {
        $value = $this->mixed($key);

        \assert(\is_float($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert nullable float.
     */
    public function assertNullableFloat(string $key): float|null
    {
        $value = $this->mixed($key);

        \assert($value === null || \is_float($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert array.
     *
     * @return array<mixed>
     */
    public function assertArray(string $key): array
    {
        $value = $this->mixed($key);

        \assert(\is_array($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert nullable array.
     *
     * @return array<mixed>|null
     */
    public function assertNullableArray(string $key): array|null
    {
        $value = $this->mixed($key);

        \assert($value === null || \is_array($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert file.
     */
    public function assertFile(string $key): UploadedFile
    {
        $value = $this->mixed($key);

        \assert($value instanceof UploadedFile, Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert nullable file.
     */
    public function assertNullableFile(string $key): UploadedFile|null
    {
        $value = $this->mixed($key);

        \assert($value === null || $value instanceof UploadedFile, Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert carbon.
     */
    public function assertCarbon(string $key): Carbon
    {
        $value = $this->mixed($key);

        \assert($value instanceof Carbon, Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert nullable carbon.
     */
    public function assertNullableCarbon(string $key): Carbon|null
    {
        $value = $this->mixed($key);

        \assert($value === null || $value instanceof Carbon, Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert object.
     */
    public function assertObject(string $key): object
    {
        $value = $this->mixed($key);

        \assert(\is_object($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert nullable object.
     */
    public function assertNullableObject(string $key): object|null
    {
        $value = $this->mixed($key);

        \assert($value === null || \is_object($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert scalar.
     */
    public function assertScalar(string $key): bool|float|int|string
    {
        $value = $this->mixed($key);

        \assert(\is_scalar($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert nullable scalar.
     */
    public function assertNullableScalar(string $key): bool|float|int|string|null
    {
        $value = $this->mixed($key);

        \assert($value === null || \is_scalar($value), Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')));

        return $value;
    }

    /**
     * Assert enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public function assertEnum(string $key, string $enum): BackedEnum
    {
        $value = $this->mixed($key);

        \assert($value instanceof $enum, Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value', 'enum')));

        return $value;
    }

    /**
     * Assert nullable enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public function assertNullableEnum(string $key, string $enum): BackedEnum|null
    {
        $value = $this->mixed($key);

        \assert($value === null || $value instanceof $enum, Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value', 'enum')));

        return $value;
    }

    /**
     * Assert instance.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function assertInstance(string $key, string $class): object
    {
        $value = $this->mixed($key);

        if ($value instanceof $class) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'class'));
    }

    /**
     * Assert nullable instance.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public function assertNullableInstance(string $key, string $class): object|null
    {
        $value = $this->mixed($key);

        if ($value === null || $value instanceof $class) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'class'));
    }

    /**
     * Assert a.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return class-string<T>
     */
    public function assertA(string $key, string $class): string
    {
        $value = $this->mixed($key);

        if (\is_string($value) && \is_a($value, $class, true)) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'class'));
    }

    /**
     * Assert nullable a.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return class-string<T>|null
     */
    public function assertNullableA(string $key, string $class): string|null
    {
        $value = $this->mixed($key);

        if ($value === null) {
            return $value;
        }

        if (\is_string($value) && \is_a($value, $class, true)) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'class'));
    }

    /**
     * Assert in.
     *
     * @template T
     *
     * @param array<T> $enum
     *
     * @return T
     */
    public function assertIn(string $key, array $enum): mixed
    {
        $value = $this->mixed($key);

        if (\in_array($value, $enum, true)) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'enum'));
    }

    /**
     * Assert not null.
     *
     * @return array<mixed>|bool|float|int|object|string
     */
    public function assertNotNull(string $key): array|bool|float|int|object|string
    {
        $value = $this->mixed($key);

        \assert(
            \is_string($value) || \is_int($value) || \is_float($value) || \is_bool($value) || \is_object($value) || \is_array($value),
            Panicker::message(__METHOD__, 'assertion failed', \compact('key', 'value')),
        );

        return $value;
    }

    /**
     * Assert null.
     */
    public function assertNull(string $key): mixed
    {
        $value = $this->mixed($key);

        if ($value !== null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value'));
        }

        return null;
    }

    /**
     * Assert BigDecimal.
     */
    public function assertBigDecimal(string $key): BigDecimal
    {
        $value = $this->mixed($key);

        if ($value instanceof BigDecimal) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value'));
    }

    /**
     * Assert nullable BigDecimal.
     */
    public function assertNullableBigDecimal(string $key): BigDecimal|null
    {
        $value = $this->mixed($key);

        if ($value === null || $value instanceof BigDecimal) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value'));
    }
}
