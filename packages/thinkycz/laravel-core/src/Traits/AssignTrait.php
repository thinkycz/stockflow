<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Traits;

use BackedEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

trait AssignTrait
{
    /**
     * Assign string.
     *
     * @return $this
     */
    public function assignString(string $key, string $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable string.
     *
     * @return $this
     */
    public function assignNullableString(string $key, string|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign bool.
     *
     * @return $this
     */
    public function assignBool(string $key, bool $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable bool.
     *
     * @return $this
     */
    public function assignNullableBool(string $key, bool|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign int.
     *
     * @return $this
     */
    public function assignInt(string $key, int $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable int.
     *
     * @return $this
     */
    public function assignNullableInt(string $key, int|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign float.
     *
     * @return $this
     */
    public function assignFloat(string $key, float $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable float.
     *
     * @return $this
     */
    public function assignNullableFloat(string $key, float|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign array.
     *
     * @param array<mixed> $value
     *
     * @return $this
     */
    public function assignArray(string $key, array $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable array.
     *
     * @param array<mixed>|null $value
     *
     * @return $this
     */
    public function assignNullableArray(string $key, array|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign file.
     *
     * @return $this
     */
    public function assignFile(string $key, UploadedFile $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable file.
     *
     * @return $this
     */
    public function assignNullableFile(string $key, UploadedFile|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign carbon.
     *
     * @return $this
     */
    public function assignCarbon(string $key, Carbon $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable carbon.
     *
     * @return $this
     */
    public function assignNullableCarbon(string $key, Carbon|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign object.
     *
     * @return $this
     */
    public function assignObject(string $key, object $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable object.
     *
     * @return $this
     */
    public function assignNullableObject(string $key, object|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign scalar.
     *
     * @return $this
     */
    public function assignScalar(string $key, bool|float|int|string $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable scalar.
     *
     * @return $this
     */
    public function assignNullableScalar(string $key, bool|float|int|string|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     * @param T $value
     *
     * @return $this
     */
    public function assignEnum(string $key, string $enum, BackedEnum $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     * @param T|null $value
     *
     * @return $this
     */
    public function assignNullableEnum(string $key, string $enum, BackedEnum|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign instance.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @param T $value
     *
     * @return $this
     */
    public function assignInstance(string $key, string $class, object $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable instance.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     * @return $this
     */
    public function assignNullableInstance(string $key, string $class, object|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign a.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @param class-string<T> $value
     *
     * @return $this
     */
    public function assignA(string $key, string $class, string $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign nullable a.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @param class-string<T>|null $value
     *
     * @return $this
     */
    public function assignNullableA(string $key, string $class, string|null $value): static
    {
        return $this->assign($key, $value);
    }

    /**
     * Assign in.
     *
     * @template T
     *
     * @param array<T> $enum
     * @param T $value
     *
     * @return $this
     */
    public function assignIn(string $key, array $enum, mixed $value): static
    {
        return $this->assign($key, Typer::assertIn($value, $enum));
    }
}
