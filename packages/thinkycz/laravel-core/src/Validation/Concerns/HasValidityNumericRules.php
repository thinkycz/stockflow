<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Concerns;

use Thinkycz\LaravelCore\Support\Typer;

trait HasValidityNumericRules
{
    /**
     * Add between rule.
     *
     * @return $this
     */
    public function between(float|int $min, float|int $max): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        Typer::assert($max >= $min);

        return $this->addRule('between', [$min, $max]);
    }

    /**
     * Add decimal rule.
     *
     * @return $this
     */
    public function decimal(int $min, int|null $max = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        Typer::assert($max === null || $max >= $min);

        return $this->addRule('decimal', $max === null ? [$min] : [$min, $max]);
    }

    /**
     * Add digits rule.
     *
     * @return $this
     */
    public function digits(int $length): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('digits', [$length]);
    }

    /**
     * Add max_digits rule.
     *
     * @return $this
     */
    public function maxDigits(int $max): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('max_digits', [$max]);
    }

    /**
     * Add min_digits rule.
     *
     * @return $this
     */
    public function minDigits(int $min): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('min_digits', [$min]);
    }

    /**
     * Add digits_between rule.
     *
     * @return $this
     */
    public function digitsBetween(int $minLength, int $maxLength): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        Typer::assert($maxLength >= $minLength);

        return $this->addRule('digits_between', [$minLength, $maxLength]);
    }

    /**
     * Add gt rule.
     *
     * @return $this
     */
    public function gt(string $field): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('gt', [$field]);
    }

    /**
     * Add gte rule.
     *
     * @return $this
     */
    public function gte(string $field): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('gte', [$field]);
    }

    /**
     * Add integer rule.
     *
     * @return $this
     */
    public function integer(int|null $max, int|null $min): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->integer = true;

        Typer::assert(
            $this->array === false && $this->collection === false && $this->boolean === false && $this->file === false && $this->numeric === false && $this->string === false,
            'validation type cross',
        );

        if ($min !== null && $max !== null) {
            Typer::assert($min <= $max);
        }

        if ($min !== null && $min === $max) {
            $this->size($min);
        } else {
            if ($min !== null) {
                $this->min($min);
            }

            if ($max !== null) {
                $this->max($max);
            }
        }

        return $this;
    }

    /**
     * Add lt rule.
     *
     * @return $this
     */
    public function lt(string $field): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('lt', [$field]);
    }

    /**
     * Add lte rule.
     *
     * @return $this
     */
    public function lte(string $field): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('lte', [$field]);
    }

    /**
     * Add max rule.
     *
     * @return $this
     */
    public function max(float|int $max): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('max', [$max]);
    }

    /**
     * Add min rule.
     *
     * @return $this
     */
    public function min(float|int $min): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('min', [$min]);
    }

    /**
     * Add multiple_of rule.
     *
     * @return $this
     */
    public function multipleOf(float|int $multipleOf): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('multiple_of', [$multipleOf]);
    }

    /**
     * Add numeric rule.
     *
     * @return $this
     */
    public function numeric(float|null $max, float|null $min): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->numeric = true;

        Typer::assert(
            $this->array === false && $this->collection === false && $this->boolean === false && $this->file === false && $this->integer === false && $this->string === false,
            'validation type cross',
        );

        if ($min !== null && $max !== null) {
            Typer::assert($min <= $max);
        }

        if ($max !== null && $max === $min) {
            $this->size($max);
        } else {
            if ($min !== null) {
                $this->min($min);
            }

            if ($max !== null) {
                $this->max($max);
            }
        }

        return $this;
    }

    /**
     * Add size rule.
     *
     * @return $this
     */
    public function size(float|int $size): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('size', [$size]);
    }

    /**
     * Add tiny int rules.
     *
     * @return $this
     */
    public function tinyInt(int|null $max = null, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= static::TINY_INT_MIN;
        $max ??= static::TINY_INT_MAX;

        Typer::assert($min >= static::TINY_INT_MIN);
        Typer::assert($max <= static::TINY_INT_MAX);

        return $this->integer($max, $min);
    }

    /**
     * Add unsigned tiny int rules.
     *
     * @return $this
     */
    public function unsignedTinyInt(int|null $max = null, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= static::UNSIGNED_TINY_INT_MIN;
        $max ??= static::UNSIGNED_TINY_INT_MAX;

        Typer::assert($min >= static::UNSIGNED_TINY_INT_MIN);
        Typer::assert($max <= static::UNSIGNED_TINY_INT_MAX);

        return $this->integer($max, $min);
    }

    /**
     * Add small int rules.
     *
     * @return $this
     */
    public function smallInt(int|null $max = null, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= static::SMALL_INT_MIN;
        $max ??= static::SMALL_INT_MAX;

        Typer::assert($min >= static::SMALL_INT_MIN);
        Typer::assert($max <= static::SMALL_INT_MAX);

        return $this->integer($max, $min);
    }

    /**
     * Add unsigned small int rules.
     *
     * @return $this
     */
    public function unsignedSmallInt(int|null $max = null, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= static::UNSIGNED_SMALL_INT_MIN;
        $max ??= static::UNSIGNED_SMALL_INT_MAX;

        Typer::assert($min >= static::UNSIGNED_SMALL_INT_MIN);
        Typer::assert($max <= static::UNSIGNED_SMALL_INT_MAX);

        return $this->integer($max, $min);
    }

    /**
     * Add medium int rules.
     *
     * @return $this
     */
    public function mediumInt(int|null $max = null, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= static::MEDIUM_INT_MIN;
        $max ??= static::MEDIUM_INT_MAX;

        Typer::assert($min >= static::MEDIUM_INT_MIN);
        Typer::assert($max <= static::MEDIUM_INT_MAX);

        return $this->integer($max, $min);
    }

    /**
     * Add unsigned medium int rules.
     *
     * @return $this
     */
    public function unsignedMediumInt(int|null $max = null, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= static::UNSIGNED_MEDIUM_INT_MIN;
        $max ??= static::UNSIGNED_MEDIUM_INT_MAX;

        Typer::assert($min >= static::UNSIGNED_MEDIUM_INT_MIN);
        Typer::assert($max <= static::UNSIGNED_MEDIUM_INT_MAX);

        return $this->integer($max, $min);
    }

    /**
     * Add int rules.
     *
     * @return $this
     */
    public function int(int|null $max = null, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= static::INT_MIN;
        $max ??= static::INT_MAX;

        Typer::assert($min >= static::INT_MIN);
        Typer::assert($max <= static::INT_MAX);

        return $this->integer($max, $min);
    }

    /**
     * Add unsigned int rules.
     *
     * @return $this
     */
    public function unsignedInt(int|null $max = null, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= static::UNSIGNED_INT_MIN;
        $max ??= static::UNSIGNED_INT_MAX;

        Typer::assert($min >= static::UNSIGNED_INT_MIN);
        Typer::assert($max <= static::UNSIGNED_INT_MAX);

        return $this->integer($max, $min);
    }

    /**
     * Add big int rules.
     *
     * @return $this
     */
    public function bigInt(int|null $max = null, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= static::BIG_INT_MIN;
        $max ??= static::BIG_INT_MAX;

        Typer::assert($min >= static::BIG_INT_MIN);

        return $this->integer($max, $min);
    }

    /**
     * Add unsigned big int rules.
     *
     * @return $this
     */
    public function unsignedBigInt(int|null $max = null, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= static::UNSIGNED_BIG_INT_MIN;
        $max ??= static::UNSIGNED_BIG_INT_MAX;

        Typer::assert($min >= static::UNSIGNED_BIG_INT_MIN);

        return $this->integer($max, $min);
    }

    /**
     * Add unsigned rules.
     *
     * @return $this
     */
    public function unsigned(int|null $max, int|null $min): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= 0;

        Typer::assert($min >= 0);

        return $this->integer($max, $min);
    }

    /**
     * Add signed rules.
     *
     * @return $this
     */
    public function signed(int|null $max, int|null $min): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->integer($max, $min);
    }

    /**
     * Add positive rules.
     *
     * @return $this
     */
    public function positive(int|null $max, int|null $min): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $min ??= 1;

        Typer::assert($min >= 1);

        return $this->integer($max, $min);
    }
}
