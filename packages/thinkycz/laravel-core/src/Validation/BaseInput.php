<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation;

use ArrayIterator;
use Illuminate\Contracts\Support\ValidatedData;
use Illuminate\Support\Arr;
use Thinkycz\LaravelCore\Traits\AssertTrait;
use Thinkycz\LaravelCore\Traits\ParserTrait;
use Thinkycz\LaravelCore\Traits\ParseTrait;
use Traversable;

class BaseInput implements ValidatedData
{
    use AssertTrait;
    use ParserTrait;
    use ParseTrait;

    /**
     * @param array<array-key, mixed> $input
     */
    public function __construct(public array $input = []) {}

    /**
     * @param array<array-key, mixed>|string $keys
     */
    public function has(array|string $keys): bool
    {
        return Arr::has($this->input, $keys);
    }

    /**
     * @param array<array-key, mixed>|string $keys
     */
    public function missing(array|string $keys): bool
    {
        return !$this->has($keys);
    }

    /**
     * @param array<array-key, mixed>|string $keys
     *
     * @return array<array-key, mixed>
     */
    public function only(array|string $keys): array
    {
        return Arr::only($this->input, $keys);
    }

    /**
     * @param array<array-key, mixed>|string $keys
     *
     * @return array<array-key, mixed>
     */
    public function except(array|string $keys): array
    {
        return Arr::except($this->input, $keys);
    }

    /**
     * @param array<array-key, mixed> $items
     */
    public function merge(array $items): static
    {
        $this->input = \array_replace($this->input, $items);

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return $this->input;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->input;
    }

    /**
     * Key exists.
     */
    public function exists(int|string $key): bool
    {
        return Arr::exists($this->input, $key);
    }

    /**
     * @param array<array-key, string>|string $keys
     */
    public function hasAny(array|string $keys): bool
    {
        return Arr::hasAny($this->input, $keys);
    }

    /**
     * Is filled.
     */
    public function filled(int|string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Is not filled.
     */
    public function isNotFilled(int|string $key): bool
    {
        return $this->get($key) === null;
    }

    /**
     * @param array<array-key, string>|string $keys
     */
    public function anyFilled(array|string $keys): bool
    {
        if (\is_string($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $key) {
            if ($this->filled($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array-key>
     */
    public function keys(): array
    {
        return \array_keys($this->input);
    }

    /**
     * Input.
     */
    public function input(int|string|null $key = null, mixed $default = null): mixed
    {
        return Arr::get($this->input, $key) ?? $default;
    }

    /**
     * Resolve value from data array.
     */
    public function get(int|string $key, mixed $default = null): mixed
    {
        return Arr::get($this->input, $key) ?? $default;
    }

    /**
     * Attribute is null.
     */
    public function isNull(int|string $key): bool
    {
        return $this->get($key) === null;
    }

    /**
     * Attribute is not null.
     */
    public function isNotNull(int|string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Mixed getter.
     */
    public function mixed(int|string|null $key = null): mixed
    {
        return Arr::get($this->input, $key);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->input);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        \assert(\is_int($offset) || \is_string($offset));

        return isset($this->input[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        \assert(\is_int($offset) || \is_string($offset));

        return $this->input[$offset];
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        \assert($offset === null || \is_int($offset) || \is_string($offset));

        if ($offset === null) {
            $this->input[] = $value;

            return;
        }

        $this->input[$offset] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        \assert(\is_int($offset) || \is_string($offset));

        unset($this->input[$offset]);
    }
}
