<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Concerns;

use Illuminate\Database\Schema\Builder as SchmeaBuilder;
use Illuminate\Validation\Rules\Password;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

trait HasValidityStringRules
{
    /**
     * Add active_url rule or url when testing.
     *
     * @return $this
     */
    public function activeUrl(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        if (Config::inject()->appEnvIs(['testing'])) {
            return $this->addRule('url');
        }

        return $this->addRule('active_url');
    }

    /**
     * Add after rule.
     *
     * @return $this
     */
    public function after(string $dateOrField): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('after', [$dateOrField]);
    }

    /**
     * Add after_or_equal rule.
     *
     * @return $this
     */
    public function afterOrEqual(string $dateOrField): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('after_or_equal', [$dateOrField]);
    }

    /**
     * Add alpha rule.
     *
     * @return $this
     */
    public function alpha(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('alpha');
    }

    /**
     * Add alpha_dash rule.
     *
     * @return $this
     */
    public function alphaDash(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('alpha_dash');
    }

    /**
     * Add alpha_num rule.
     *
     * @return $this
     */
    public function alphaNum(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('alpha_num');
    }

    /**
     * Add ascii rule.
     *
     * @return $this
     */
    public function ascii(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('ascii');
    }

    /**
     * Add before rule.
     *
     * @return $this
     */
    public function before(string $dateOrField): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('before', [$dateOrField]);
    }

    /**
     * Add before_or_equal rule.
     *
     * @return $this
     */
    public function beforeOrEqual(string $dateOrField): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('before_or_equal', [$dateOrField]);
    }

    /**
     * Add date rule.
     *
     * @return $this
     */
    public function date(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('date');
    }

    /**
     * Add date_equals rule.
     *
     * @return $this
     */
    public function dateEquals(string $date): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('date_equals', [$date]);
    }

    /**
     * Add date_format rule.
     *
     * @return $this
     */
    public function dateFormat(string $dateFormat = 'Y-m-d'): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('date_format', [$dateFormat]);
    }

    /**
     * Add doesnt_start_with rule.
     *
     * @param array<int, string> $ends
     *
     * @return $this
     */
    public function doesntStartWith(array $ends): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('doesnt_start_with', $ends);
    }

    /**
     * Add doesnt_end_with rule.
     *
     * @param array<int, string> $ends
     *
     * @return $this
     */
    public function doesntEndWith(array $ends): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('doesnt_end_with', $ends);
    }

    /**
     * Add email rule.
     *
     * @return $this
     */
    public function email(bool $filterUnicode = true, bool $strict = true, bool $dns = false, bool $rfc = false, bool $spoof = true, bool $filter = false): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        Typer::assert(!$filterUnicode || !$filter, 'filter and filter_unicode can not coexist');
        Typer::assert(!$strict || !$rfc, 'strict and rfc can not coexist');

        if (Config::inject()->appEnvIs(['testing'])) {
            return $this->addRule('email');
        }

        $options = [];

        if ($filter) {
            $options[] = 'filter';
        }

        if ($filterUnicode) {
            $options[] = 'filter_unicode';
        }

        if ($strict) {
            $options[] = 'strict';
        }

        if ($dns) {
            $options[] = 'dns';
        }

        if ($rfc) {
            $options[] = 'rfc';
        }

        if ($spoof) {
            $options[] = 'spoof';
        }

        return $this->addRule('email', $options);
    }

    /**
     * Add ends_with rule.
     *
     * @param array<int, string> $ends
     *
     * @return $this
     */
    public function endsWith(array $ends): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('ends_with', $ends);
    }

    /**
     * Add ip rule.
     *
     * @return $this
     */
    public function ip(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('ip');
    }

    /**
     * Add ipv4 rule.
     *
     * @return $this
     */
    public function ipv4(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('ipv4');
    }

    /**
     * Add ipv6 rule.
     *
     * @return $this
     */
    public function ipv6(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('ipv6');
    }

    /**
     * Add mac_address rule.
     *
     * @return $this
     */
    public function macAddress(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('mac_address');
    }

    /**
     * Add json rule.
     *
     * @return $this
     */
    public function json(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('json');
    }

    /**
     * Add lowercase rule.
     *
     * @return $this
     */
    public function lowercase(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('lowercase');
    }

    /**
     * Add uppercase rule.
     *
     * @return $this
     */
    public function uppercase(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('uppercase');
    }

    /**
     * Add not_regex rule.
     *
     * @return $this
     */
    public function notRegex(string $pattern): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('not_regex', [$pattern]);
    }

    /**
     * Add password rule.
     *
     * @return $this
     */
    public function passwordRule(int $min): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(new Password($min));
    }

    /**
     * Add password rule.
     *
     * @return $this
     */
    public function password(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule(Password::default());
    }

    /**
     * Add regex rule.
     *
     * @return $this
     */
    public function regex(string $pattern): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('regex', [$pattern]);
    }

    /**
     * Add starts_with rule.
     *
     * @param array<int, string> $startsWith
     *
     * @return $this
     */
    public function startsWith(array $startsWith): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('starts_with', $startsWith);
    }

    /**
     * Add string rule.
     *
     * @return $this
     */
    public function string(int|null $max, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->string = true;

        Typer::assert(
            $this->array === false && $this->collection === false && $this->boolean === false && $this->file === false && $this->integer === false && $this->numeric === false,
            'validation type cross',
        );

        if ($min !== null && $max !== null) {
            Typer::assert($min <= $max);
        }

        if ($max !== null && $max === $min) {
            Typer::assert($min >= 0);

            $this->size($max);
        } else {
            if ($min !== null) {
                Typer::assert($min >= 0);

                $this->min($min);
            }

            if ($max !== null) {
                Typer::assert($max >= 0);

                $this->max($max);
            }
        }

        return $this;
    }

    /**
     * Add bytes rule.
     *
     * @return $this
     */
    public function bytes(int|null $max, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $this->string = true;

        Typer::assert(
            $this->array === false && $this->collection === false && $this->boolean === false && $this->file === false && $this->integer === false && $this->numeric === false,
            'validation type cross',
        );

        if ($min !== null && $max !== null) {
            Typer::assert($min <= $max);
        }

        if ($max !== null && $max === $min) {
            Typer::assert($min >= 0);

            $this->strlen($max);
        } else {
            if ($min !== null) {
                Typer::assert($min >= 0);

                $this->strlenMin($min);
            }

            if ($max !== null) {
                Typer::assert($max >= 0);

                $this->strlenMax($max);
            }
        }

        return $this;
    }

    /**
     * Add varchar rule.
     *
     * @return $this
     */
    public function varchar(int|null $max = null, int|null $min = null): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        $max ??= SchmeaBuilder::$defaultStringLength;

        Typer::assert($max <= static::VARCHAR_MAX);

        return $this->string($max, $min);
    }

    /**
     * Add timezone rule.
     *
     * @return $this
     */
    public function timezone(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('timezone');
    }

    /**
     * Add url rule.
     *
     * @return $this
     */
    public function url(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('url');
    }

    /**
     * Add uuid rule.
     *
     * @return $this
     */
    public function uuid(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('uuid');
    }

    /**
     * Add ulid rule.
     *
     * @return $this
     */
    public function ulid(): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->addRule('ulid');
    }

    /**
     * Add strlen rule.
     *
     * @return $this
     */
    public function strlen(int $length): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        Typer::assert($length >= 0);

        return $this->addRule('strlen', [$length]);
    }

    /**
     * Add strlen_max rule.
     *
     * @return $this
     */
    public function strlenMax(int $max): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        Typer::assert($max >= 0);

        return $this->addRule('strlen_max', [$max]);
    }

    /**
     * Add strlen_min rule.
     *
     * @return $this
     */
    public function strlenMin(int $min): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        Typer::assert($min >= 0);

        return $this->addRule('strlen_min', [$min]);
    }

    /**
     * Add char rules.
     *
     * @return $this
     */
    public function char(int $length): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        return $this->string(null)->strlen($length);
    }

    /**
     * Add tiny text rules.
     *
     * @return $this
     */
    public function tinyText(int|null $max = null, int|null $min = null, bool $bytes = false): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        if ($bytes) {
            $max ??= static::TINY_TEXT_MAX;

            Typer::assert($max <= static::TINY_TEXT_MAX);

            $this->bytes($max, $min);
        } else {
            $max ??= (int) (static::TINY_TEXT_MAX / 4);

            Typer::assert($max <= (int) (static::TINY_TEXT_MAX / 4), 'text columns are in bytes');

            $this->string($max, $min);
        }

        return $this;
    }

    /**
     * Add text rules.
     *
     * @return $this
     */
    public function text(int|null $max = null, int|null $min = null, bool $bytes = false): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        if ($bytes) {
            $max ??= static::TEXT_MAX;

            Typer::assert($max <= static::TEXT_MAX);

            $this->bytes($max, $min);
        } else {
            $max ??= (int) (static::TEXT_MAX / 4);

            Typer::assert($max <= (int) (static::TEXT_MAX / 4), 'text columns are in bytes');

            $this->string($max, $min);
        }

        return $this;
    }

    /**
     * Add medium text rules.
     *
     * @return $this
     */
    public function mediumText(int|null $max = null, int|null $min = null, bool $bytes = false): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        if ($bytes) {
            $max ??= static::MEDIUM_TEXT_MAX;

            Typer::assert($max <= static::MEDIUM_TEXT_MAX);

            $this->bytes($max, $min);
        } else {
            $max ??= (int) (static::MEDIUM_TEXT_MAX / 4);

            Typer::assert($max <= (int) (static::MEDIUM_TEXT_MAX / 4), 'text columns are in bytes');

            $this->string($max, $min);
        }

        return $this;
    }

    /**
     * Add long text rules.
     *
     * @return $this
     */
    public function longText(int|null $max = null, int|null $min = null, bool $bytes = false): static
    {
        if ($this->skipNext) {
            $this->skipNext = false;

            return $this;
        }

        if ($bytes) {
            $max ??= static::LONG_TEXT_MAX;

            Typer::assert($max <= static::LONG_TEXT_MAX);

            $this->bytes($max, $min);
        } else {
            $max ??= (int) (static::LONG_TEXT_MAX / 4);

            Typer::assert($max <= (int) (static::LONG_TEXT_MAX / 4), 'text columns are in bytes');

            $this->string($max, $min);
        }

        return $this;
    }
}
