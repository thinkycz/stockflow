<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Concerns;

use Closure;
use Illuminate\Validation\Rules\Enum;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\Rules\CallbackRule;
use Thinkycz\LaravelCore\Validation\Rules\NullableVoidRule;
use Thinkycz\LaravelCore\Validation\Rules\VoidRule;
use UnitEnum;

trait HasValidityTypeRules
{
    /**
     * Add collection rule.
     *
     * @return $this
     */
    public function collection(int|null $maxItems, int|null $minItems = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->collection = true;

        Typer::assert(
            $this->array === false && $this->boolean === false && $this->file === false && $this->integer === false && $this->numeric === false && $this->string === false,
            'validation type cross',
        );

        if ($minItems !== null && $maxItems !== null) {
            Typer::assert($minItems <= $maxItems);
        }

        if ($maxItems !== null && $maxItems === $minItems) {
            Typer::assert($maxItems >= 0);

            $this->size($maxItems);
        } else {
            if ($maxItems !== null) {
                Typer::assert($maxItems >= 0);

                $this->max($maxItems);
            }

            if ($minItems !== null) {
                Typer::assert($minItems >= 0);

                $this->min($minItems);
            }
        }

        return $this;
    }

    /**
     * Add array rule.
     *
     * @param ?array<int, string> $structure
     *
     * @return $this
     */
    public function array(array|null $structure): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->array = true;

        Typer::assert(
            $this->collection === false && $this->boolean === false && $this->file === false && $this->integer === false && $this->numeric === false && $this->string === false,
            'validation type cross',
        );

        if ($structure !== null) {
            $this->addRule('array', $structure);
        }

        return $this;
    }

    /**
     * Add object rule.
     *
     * @param array<int, string> $keys
     *
     * @return $this
     */
    public function object(array $keys): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->array = true;

        Typer::assert(
            $this->collection === false && $this->boolean === false && $this->file === false && $this->integer === false && $this->numeric === false && $this->string === false,
            'validation type cross',
        );

        return $this->requiredArrayKeys($keys);
    }

    /**
     * Add boolean rule.
     *
     * @return $this
     */
    public function boolean(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->boolean = true;

        Typer::assert(
            $this->array === false && $this->collection === false && $this->file === false && $this->integer === false && $this->numeric === false && $this->string === false,
            'validation type cross',
        );

        return $this;
    }

    /**
     * Add true rule.
     *
     * @return $this
     */
    public function true(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->boolean()->in([true]);
    }

    /**
     * Add false rule.
     *
     * @return $this
     */
    public function false(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->boolean()->in([false]);
    }

    /**
     * Add enum rule.
     *
     * @param class-string<UnitEnum> $type
     *
     * @return $this
     */
    public function enumRule(string $type): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(new Enum($type));
    }

    /**
     * Add in rule.
     *
     * @param array<array-key, mixed> $values
     *
     * @return $this
     */
    public function in(array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('in', \array_values($values));
    }

    /**
     * Add in integer rule.
     *
     * @param array<int, mixed> $values
     *
     * @return $this
     */
    public function inInteger(array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->integer(null, null)->in($values);
    }

    /**
     * Add in string rule.
     *
     * @param array<array-key, mixed> $values
     *
     * @return $this
     */
    public function inString(array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->string(null)->in($values);
    }

    /**
     * Add in_array rule.
     *
     * @return $this
     */
    public function inArray(string $field): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('in_array', [$field]);
    }

    /**
     * Add not_if rule.
     *
     * @param array<int, mixed> $values
     *
     * @return $this
     */
    public function notIn(array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('not_in', $values);
    }

    /**
     * Add closure rule.
     *
     * @param Closure(string, mixed, Closure(string): void): void $closure
     *
     * @return $this
     */
    public function closure(Closure $closure): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule($closure);
    }

    /**
     * Add callback rule.
     *
     * @param Closure(mixed, mixed=): bool $callback
     *
     * @return $this
     */
    public function callback(Closure $callback, string $message = 'validation.invalid'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(new CallbackRule($callback, $message));
    }

    /**
     * Add void rule.
     *
     * @param Closure(mixed, mixed=): void $callback
     *
     * @return $this
     */
    public function void(Closure $callback): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(new VoidRule($callback));
    }

    /**
     * Add nullable void rule.
     *
     * @param Closure(mixed, mixed=): void $callback
     *
     * @return $this
     */
    public function nullableVoid(Closure $callback): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(new NullableVoidRule($callback));
    }
}
