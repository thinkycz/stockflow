<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Concerns;

use Closure;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\ProhibitedIf;
use Illuminate\Validation\Rules\RequiredIf;

trait HasValidityPresenceRules
{
    /**
     * Add accepted rule.
     *
     * @return $this
     */
    public function accepted(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('accepted')->boolean();
    }

    /**
     * Add accepted_if rule.
     *
     * @param array<array-key, mixed> $values
     *
     * @return $this
     */
    public function acceptedIf(string $field, array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('accepted_if', [$field, ...\array_values($values)])->boolean();
    }

    /**
     * Add bail rule.
     *
     * @return $this
     */
    public function bail(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->bail = true;

        return $this;
    }

    /**
     * Add confirmed rule.
     *
     * @return $this
     */
    public function confirmed(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('confirmed');
    }

    /**
     * Add current_password rule.
     *
     * @return $this
     */
    public function currentPassword(string|null $guard = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('current_password', $guard !== null ? [$guard] : null);
    }

    /**
     * Add declined rule.
     *
     * @return $this
     */
    public function declined(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('declined')->boolean();
    }

    /**
     * Add declined_if rule.
     *
     * @param array<array-key, mixed> $values
     *
     * @return $this
     */
    public function declinedIf(string $field, array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('declined_if', [$field, ...\array_values($values)])->boolean();
    }

    /**
     * Add different rule.
     *
     * @return $this
     */
    public function different(string $field): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('different', [$field]);
    }

    /**
     * Add distinct rule.
     *
     * @return $this
     */
    public function distinct(bool $strict = false, bool $ignoreCase = true): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $options = [];

        if ($strict) {
            $options[] = 'strict';
        }

        if ($ignoreCase) {
            $options[] = 'ignore_case';
        }

        return $this->addRule('distinct', $options);
    }

    /**
     * Add exclude rule.
     *
     * @return $this
     */
    public function exclude(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('exclude');
    }

    /**
     * Add exclude_if rule.
     *
     * @param array<array-key, mixed> $values
     *
     * @return $this
     */
    public function excludeIf(string $field, array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('exclude_if', [$field, ...\array_values($values)]);
    }

    /**
     * Add exclude_if rule.
     *
     * @param bool|(Closure(): bool) $condition
     *
     * @return $this
     */
    public function excludeIfRule(bool|Closure $condition): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(new ExcludeIf($condition));
    }

    /**
     * Add exclude_unless rule.
     *
     * @param array<array-key, mixed> $values
     *
     * @return $this
     */
    public function excludeUnless(string $field, array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('exclude_unless', [$field, ...\array_values($values)]);
    }

    /**
     * Add exclude_with rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function excludeWith(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('exclude_with', $fields);
    }

    /**
     * Add exclude_without rule.
     *
     * @return $this
     */
    public function excludeWithout(string $field): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('exclude_without', [$field]);
    }

    /**
     * Add filled rule.
     *
     * @return $this
     */
    public function filled(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->filled = true;

        return $this;
    }

    /**
     * Add missing rule.
     *
     * @return $this
     */
    public function missing(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->missing = true;

        return $this;
    }

    /**
     * Add missing_if rule.
     *
     * @param array<int, mixed> $values
     *
     * @return $this
     */
    public function missingIf(string $field, array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('missing_if', [$field, ...$values]);
    }

    /**
     * Add missing_unless rule.
     *
     * @param array<int, mixed> $values
     *
     * @return $this
     */
    public function missingUnless(string $field, array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('missing_unless', [$field, ...$values]);
    }

    /**
     * Add missing_with rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function missingWith(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('missing_with', $fields);
    }

    /**
     * Add missing_with_all rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function missingWithAll(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('missing_with_all', $fields);
    }

    /**
     * Add nullable rule.
     *
     * @return $this
     */
    public function nullable(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->nullable = true;

        return $this;
    }

    /**
     * Add present rule.
     *
     * @return $this
     */
    public function present(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('present');
    }

    /**
     * Add prohibited rule.
     *
     * @return $this
     */
    public function prohibited(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->prohibited = true;

        return $this;
    }

    /**
     * Add prohibited_if rule.
     *
     * @param array<int, mixed> $values
     *
     * @return $this
     */
    public function prohibitedIf(string $field, array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('prohibited_if', [$field, ...$values]);
    }

    /**
     * Add prohibited_unless rule.
     *
     * @param array<int, mixed> $values
     *
     * @return $this
     */
    public function prohibitedUnless(string $field, array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('prohibited_unless', [$field, ...$values]);
    }

    /**
     * Add prohibits rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function prohibits(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('prohibits', $fields);
    }

    /**
     * Add required rule.
     *
     * @return $this
     */
    public function required(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->required = true;

        return $this;
    }

    /**
     * Add required_if rule.
     *
     * @param array<int, mixed> $values
     *
     * @return $this
     */
    public function requiredIf(string $field, array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('required_if', [$field, ...$values]);
    }

    /**
     * Add required_unless rule.
     *
     * @param array<int, mixed> $values
     *
     * @return $this
     */
    public function requiredUnless(string $field, array $values): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('required_unless', [$field, ...$values]);
    }

    /**
     * Add required_with rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function requiredWith(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('required_with', $fields);
    }

    /**
     * Add required_with_all rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function requiredWithAll(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('required_with_all', $fields);
    }

    /**
     * Add required_without rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function requiredWithout(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('required_without', $fields);
    }

    /**
     * Add required_without_all rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function requiredWithoutAll(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('required_without_all', $fields);
    }

    /**
     * Add required_array_keys rule.
     *
     * @param array<int, string> $keys
     *
     * @return $this
     */
    public function requiredArrayKeys(array $keys): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('required_array_keys', $keys);
    }

    /**
     * Add same rule.
     *
     * @return $this
     */
    public function same(string $field): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('same', [$field]);
    }

    /**
     * Add required_if rule.
     *
     * @param bool|(Closure(): bool) $condition
     *
     * @return $this
     */
    public function requiredIfRule(bool|callable $condition): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(new RequiredIf($condition));
    }

    /**
     * Add sometimes rule.
     *
     * @return $this
     */
    public function sometimes(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->sometimes = true;

        return $this;
    }

    /**
     * Add prohibited_with rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function prohibitedWith(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('prohibited_with', $fields);
    }

    /**
     * Add prohibited_without rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function prohibitedWithout(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('prohibited_without', $fields);
    }

    /**
     * Add prohibited_with_all rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function prohibitedWithAll(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('prohibited_with_all', $fields);
    }

    /**
     * Add prohibited_without_all rule.
     *
     * @param array<int, string> $fields
     *
     * @return $this
     */
    public function prohibitedWithoutAll(array $fields): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('prohibited_without_all', $fields);
    }

    /**
     * Add prohibited if rule.
     *
     * @param bool|(Closure(): bool) $condition
     *
     * @return $this
     */
    public function prohibitedIfRule(bool|Closure $condition): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(new ProhibitedIf($condition));
    }
}
