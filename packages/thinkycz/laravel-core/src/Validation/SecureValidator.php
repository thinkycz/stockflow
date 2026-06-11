<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation;

use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\ClosureValidationRule;
use Illuminate\Validation\InvokableValidationRule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\ImageFile;
use Illuminate\Validation\Rules\Password;
use Thinkycz\LaravelCore\Support\Csv;
use Thinkycz\LaravelCore\Support\Typer;
use Thinkycz\LaravelCore\Validation\Rules\CallbackRule;
use Thinkycz\LaravelCore\Validation\Rules\CzBankAccountNumberRule;
use Thinkycz\LaravelCore\Validation\Rules\IcoRule;
use Thinkycz\LaravelCore\Validation\Rules\PostCodeRule;
use Thinkycz\LaravelCore\Validation\Rules\RecaptchaRule;

class SecureValidator extends Validator
{
    /**
     * Validation message map.
     *
     * @var array<string, array<string, string>|string>
     */
    public const MSGS = [
        // Standard validation rules in the same order as in lang/cs/validation.php
        'accepted' => 'accepted',
        'accepted_if' => 'accepted_if::other,:value',
        'active_url' => 'active_url',
        'after' => 'after::date',
        'after_or_equal' => 'after_or_equal::date',
        'alpha' => 'alpha',
        'alpha_dash' => 'alpha_dash',
        'alpha_num' => 'alpha_num',
        'any_of' => 'any_of',
        'array' => 'array',
        'ascii' => 'ascii',
        'before' => 'before::date',
        'before_or_equal' => 'before_or_equal::date',
        'between' => [
            'array' => 'between_array::min,:max',
            'file' => 'between_file::min,:max',
            'numeric' => 'between_numeric::min,:max',
            'string' => 'between_string::min,:max',
        ],
        'boolean' => 'boolean',
        'can' => 'can',
        'confirmed' => 'confirmed',
        'contains' => 'contains',
        'current_password' => 'current_password',
        'date' => 'date',
        'date_equals' => 'date_equals::date',
        'date_format' => 'date_format::format',
        'decimal' => 'decimal::decimal',
        'declined' => 'declined',
        'declined_if' => 'declined_if::other,:value',
        'different' => 'different::other',
        'digits' => 'digits::digits',
        'digits_between' => 'digits_between::min,:max',
        'dimensions' => 'dimensions',
        'distinct' => 'distinct',
        'doesnt_end_with' => 'doesnt_end_with::values',
        'doesnt_start_with' => 'doesnt_start_with::values',
        'email' => 'email',
        'ends_with' => 'ends_with::values',
        'enum' => 'enum',
        'exists' => 'exists',
        'extensions' => 'extensions::values',
        'file' => 'file',
        'filled' => 'filled',
        'gt' => [
            'array' => 'gt_array::value',
            'file' => 'gt_file::value',
            'numeric' => 'gt_numeric::value',
            'string' => 'gt_string::value',
        ],
        'gte' => [
            'array' => 'gte_array::value',
            'file' => 'gte_file::value',
            'numeric' => 'gte_numeric::value',
            'string' => 'gte_string::value',
        ],
        'hex_color' => 'hex_color',
        'image' => 'image',
        'in' => 'in',
        'in_array' => 'in_array::other',
        'in_array_keys' => 'in_array_keys::values',
        'integer' => 'integer',
        'ip' => 'ip',
        'ipv4' => 'ipv4',
        'ipv6' => 'ipv6',
        'json' => 'json',
        'list' => 'list',
        'lowercase' => 'lowercase',
        'lt' => [
            'array' => 'lt_array::value',
            'file' => 'lt_file::value',
            'numeric' => 'lt_numeric::value',
            'string' => 'lt_string::value',
        ],
        'lte' => [
            'array' => 'lte_array::value',
            'file' => 'lte_file::value',
            'numeric' => 'lte_numeric::value',
            'string' => 'lte_string::value',
        ],
        'mac_address' => 'mac_address',
        'max' => [
            'array' => 'max_array::max',
            'file' => 'max_file::max',
            'numeric' => 'max_numeric::max',
            'string' => 'max_string::max',
        ],
        'max_digits' => 'max_digits::max',
        'mimes' => 'mimes::values',
        'mimetypes' => 'mimetypes::values',
        'min' => [
            'array' => 'min_array::min',
            'file' => 'min_file::min',
            'numeric' => 'min_numeric::min',
            'string' => 'min_string::min',
        ],
        'min_digits' => 'min_digits::min',
        'missing' => 'missing',
        'missing_if' => 'missing_if::other,:values',
        'missing_unless' => 'missing_unless::other,:values',
        'missing_with' => 'missing_with::values',
        'missing_with_all' => 'missing_with_all::values',
        'multiple_of' => 'multiple_of::value',
        'not_in' => 'not_in',
        'not_regex' => 'not_regex',
        'numeric' => 'numeric',
        'password' => [
            'letters' => 'password_letters',
            'mixed' => 'password_mixed',
            'numbers' => 'password_numbers',
            'symbols' => 'password_symbols',
            'uncompromised' => 'password_uncompromised',
        ],
        'present' => 'present',
        'present_if' => 'present_if::other,:value',
        'present_unless' => 'present_unless::other,:value',
        'present_with' => 'present_with::values',
        'present_with_all' => 'present_with_all::values',
        'prohibited' => 'prohibited',
        'prohibited_if' => 'prohibited_if::other,:value',
        'prohibited_if_accepted' => 'prohibited_if_accepted::other',
        'prohibited_if_declined' => 'prohibited_if_declined::other',
        'prohibited_unless' => 'prohibited_unless::other,:values',
        'prohibits' => 'prohibits::other',
        'regex' => 'regex',
        'required' => 'required',
        'required_array_keys' => 'required_array_keys::values',
        'required_if' => 'required_if::other,:value',
        'required_if_accepted' => 'required_if_accepted::other',
        'required_if_declined' => 'required_if_declined::other',
        'required_unless' => 'required_unless::other,:values',
        'required_with' => 'required_with::values',
        'required_with_all' => 'required_with_all::values',
        'required_without' => 'required_without::values',
        'required_without_all' => 'required_without_all::values',
        'same' => 'same::other',
        'size' => [
            'array' => 'size_array::size',
            'file' => 'size_file::size',
            'numeric' => 'size_numeric::size',
            'string' => 'size_string::size',
        ],
        'starts_with' => 'starts_with::values',
        'string' => 'string',
        'timezone' => 'timezone',
        'unique' => 'unique',
        'uploaded' => 'uploaded',
        'uppercase' => 'uppercase',
        'url' => 'url',
        'ulid' => 'ulid',
        'uuid' => 'uuid',

        // Additional validation rules not in the standard Laravel validation
        'prohibited_with' => 'prohibited_with::values',
        'prohibited_with_all' => 'prohibited_with_all::values',
        'prohibited_without' => 'prohibited_without::values',
        'prohibited_without_all' => 'prohibited_without_all::values',
        'strlen' => 'strlen::size',
        'strlen_max' => 'strlen_max::max',
        'strlen_min' => 'strlen_min::min',

        // Special messages
        'throttled' => 'throttled::seconds',
        'invalid' => 'invalid',
        'fallback' => 'fallback',

        // Authentication messages
        'auth.failed' => 'auth_failed',
        'auth.password' => 'auth_password',
        'auth.throttle' => 'auth_throttle::seconds',
        'auth.blocked' => 'auth_blocked',

        // Password reset messages
        'passwords.reset' => 'passwords_reset',
        'passwords.sent' => 'passwords_sent',
        'passwords.throttled' => 'passwords_throttled',
        'passwords.token' => 'passwords_token',
        'passwords.user' => 'passwords_user',

        // Custom rules
        'collection' => 'collection',

        ClosureValidationRule::class => 'invalid',
        InvokableValidationRule::class => 'invalid',
        Enum::class => 'enum',
        File::class => 'file',
        Password::class => 'regex',
        ImageFile::class => 'image',

        CallbackRule::class => 'invalid',
        CzBankAccountNumberRule::class => 'regex',
        IcoRule::class => 'regex',
        PostCodeRule::class => 'regex',
        RecaptchaRule::class => 'invalid',
    ];

    /**
     * @var array<string, array<string, string>|string>
     */
    public static array $customMsgs = [];

    /**
     * @inheritDoc
     */
    public function passes(): bool
    {
        $this->messages = new MessageBag();

        foreach (\array_keys(\array_diff_key($this->dot($this->data), $this->rules)) as $attribute) {
            $this->addFailure((string) $attribute, 'Missing');
        }

        return $this->messages->isEmpty() && parent::passes();
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public function dot(array $data, string $prepend = ''): array
    {
        $results = [];

        foreach ($data as $key => $value) {
            $results[$prepend . $key] = null;

            if (\is_array($value)) {
                $results = \array_replace($results, $this->dot($value, $prepend . $key . '.'));
            }
        }

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function getDisplayableValue(mixed $attribute, mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        return (string) Typer::assertScalar($value);
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed> $parameters
     */
    public function makeReplacements(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        $message = parent::makeReplacements($message, $attribute, $rule, $parameters);

        if (!\str_contains($message, ':')) {
            return $message;
        }

        [$message, $args] = \explode(':', $message, 2);

        foreach ($parameters as $key => $value) {
            if (\is_int($key)) {
                continue;
            }

            if (!\is_scalar($value)) {
                continue;
            }

            $args = \str_replace(':' . $key, (string) $value, $args);
        }

        return $message . ':' . $args;
    }

    /**
     * @inheritDoc
     */
    protected function shouldStopValidating(mixed $attribute): bool
    {
        if ($this->messages->has($this->replacePlaceholderInString($attribute))) {
            return true;
        }

        return parent::shouldStopValidating($attribute);
    }

    /**
     * @inheritDoc
     */
    protected function getAttributeFromTranslations(mixed $name): string
    {
        return $name;
    }

    /**
     * @inheritDoc
     */
    protected function getInlineMessage(mixed $attribute, mixed $rule): string
    {
        $rule = Typer::assertString($rule);
        $snake = Typer::assertString(Str::snake($rule));

        if (\in_array($rule, $this->sizeRules, true)) {
            $type = $this->getAttributeType($attribute);

            $message = static::$customMsgs[$snake][$type] ?? (static::MSGS[$snake][$type] ?? 'fallback');
        } else {
            $message = static::$customMsgs[$snake] ?? (static::MSGS[$snake] ?? 'fallback');
        }

        return Typer::assertString($message);
    }

    /**
     * @inheritDoc
     *
     * @param ?array<mixed> $source
     */
    protected function getFromLocalArray(mixed $attribute, mixed $lowerRule, mixed $source = null): string
    {
        $lowerRule = Typer::assertString($lowerRule);
        $message = static::$customMsgs[$lowerRule] ?? (static::MSGS[$lowerRule] ?? 'fallback');

        return Typer::assertString($message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceProhibitedWith(string $message, string $attribute, string $rule, array $parameters): string
    {
        return \str_replace(':values', Csv::line($this->getAttributeList($parameters)), $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceMissingWith(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        return \str_replace(':values', Csv::line($this->getAttributeList($parameters)), $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceIn(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        foreach ($parameters as &$parameter) {
            $parameter = $this->getDisplayableValue($attribute, $parameter);
        }

        return \str_replace(':values', Csv::line($parameters), $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceRequiredArrayKeys(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        foreach ($parameters as &$parameter) {
            $parameter = $this->getDisplayableValue($attribute, $parameter);
        }

        return \str_replace(':values', Csv::line($parameters), $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceMimetypes(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        return \str_replace(':values', Csv::line($parameters), $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceMimes(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        return \str_replace(':values', Csv::line($parameters), $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceRequiredWith(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        return \str_replace(':values', Csv::line($this->getAttributeList($parameters)), $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceRequiredUnless(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        $other = $this->getDisplayableAttribute($parameters[0]);

        $values = [];

        foreach (\array_slice($parameters, 1) as $value) {
            $values[] = $this->getDisplayableValue($parameters[0], $value);
        }

        return \str_replace([':other', ':values'], [$other, Csv::line($values)], $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceProhibitedUnless(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        $other = $this->getDisplayableAttribute($parameters[0]);

        $values = [];

        foreach (\array_slice($parameters, 1) as $value) {
            $values[] = $this->getDisplayableValue($parameters[0], $value);
        }

        return \str_replace([':other', ':values'], [$other, Csv::line($values)], $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceProhibits(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        return \str_replace(':other', Csv::line($this->getAttributeList($parameters)), $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceEndsWith(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        foreach ($parameters as &$parameter) {
            $parameter = $this->getDisplayableValue($attribute, $parameter);
        }

        return \str_replace(':values', Csv::line($parameters), $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceDoesntEndWith(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        foreach ($parameters as &$parameter) {
            $parameter = $this->getDisplayableValue($attribute, $parameter);
        }

        return \str_replace(':values', Csv::line($parameters), $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceStartsWith(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        foreach ($parameters as &$parameter) {
            $parameter = $this->getDisplayableValue($attribute, $parameter);
        }

        return \str_replace(':values', Csv::line($parameters), $message);
    }

    /**
     * @inheritDoc
     */
    protected function replaceDoesntStartWith(mixed $message, mixed $attribute, mixed $rule, mixed $parameters): string
    {
        foreach ($parameters as &$parameter) {
            $parameter = $this->getDisplayableValue($attribute, $parameter);
        }

        return \str_replace(':values', Csv::line($parameters), $message);
    }
}
