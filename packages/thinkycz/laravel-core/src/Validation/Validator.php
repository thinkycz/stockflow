<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation;

use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Contracts\Validation\Factory as ValidationFactoryContract;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Validator as IlluminateValidator;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class Validator extends IlluminateValidator
{
    /**
     * @inheritDoc
     *
     * @param array<mixed> $data
     * @param array<mixed> $rules
     * @param array<mixed> $messages
     * @param array<mixed> $customAttributes
     */
    public function __construct(TranslatorContract $translator, array $data, array $rules, array $messages = [], array $customAttributes = [])
    {
        parent::__construct($translator, $data, $rules, $messages, $customAttributes);

        $this->dependentRules = \array_merge($this->dependentRules, ['ProhibitedWith', 'ProhibitedWithAll', 'ProhibitedWithout', 'ProhibitedWithoutAll']);
    }

    /**
     * Extend factory with custom resolver.
     *
     * @param class-string<IlluminateValidator> $validator
     */
    public static function extend(ValidationFactoryContract $factory, string $validator): void
    {
        Typer::assertInstance($factory, ValidationFactory::class)->resolver(static function (TranslatorContract $translator, array $data, array $rules, array $messages, array $attributes) use (
            $validator,
        ): IlluminateValidator {
            return new $validator($translator, $data, $rules, $messages, $attributes);
        });
    }

    /**
     * Inject factory with custom resolver.
     */
    public static function clone(ValidationFactoryContract $factory): ValidationFactoryContract
    {
        $clonedFactory = clone Typer::assertInstance($factory, ValidationFactory::class);

        static::extend($clonedFactory, static::class);

        return $clonedFactory;
    }

    /**
     * Get input from headers.
     */
    public static function fromHeaders(): IlluminateValidator
    {
        return Resolver::resolveValidator(Resolver::resolveRequest()->headers->all());
    }

    /**
     * Get input from request.
     */
    public static function fromRequest(): IlluminateValidator
    {
        return Resolver::resolveValidator(Resolver::resolveRequest()->all());
    }

    /**
     * Get input from route.
     */
    public static function fromRoute(): IlluminateValidator
    {
        return Resolver::resolveValidator(Resolver::resolveRoute()->parameters ?? []);
    }

    /**
     * Get input from cookies.
     */
    public static function fromCookies(): IlluminateValidator
    {
        return Resolver::resolveValidator(Resolver::resolveRequest()->cookies->all());
    }

    /**
     * Get input from files.
     */
    public static function fromFiles(): IlluminateValidator
    {
        return Resolver::resolveValidator(Resolver::resolveRequest()->allFiles());
    }

    /**
     * Get input from request attributes.
     */
    public static function fromAttributes(): IlluminateValidator
    {
        return Resolver::resolveValidator(Resolver::resolveRequest()->attributes->all());
    }

    /**
     * Get input from session.
     */
    public static function fromSession(): IlluminateValidator
    {
        return Resolver::resolveValidator(Resolver::resolveRequest()->session()->all());
    }

    /**
     * Validate that an attribute is prohibited when any other attribute exists.
     *
     * @param array<int, string> $parameters
     */
    public function validateProhibitedWith(string $attribute, mixed $value, array $parameters): bool
    {
        foreach ($parameters as $key) {
            if ($this->validateRequired($key, $this->getValue($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate that an attribute is prohibited when any other attribute not exists.
     *
     * @param array<int, string> $parameters
     */
    public function validateProhibitedWithout(string $attribute, mixed $value, array $parameters): bool
    {
        foreach ($parameters as $key) {
            if (!$this->validateRequired($key, $this->getValue($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate that an attribute is prohibited when all other attribute exists.
     *
     * @param array<int, string> $parameters
     */
    public function validateProhibitedWithAll(string $attribute, mixed $value, array $parameters): bool
    {
        foreach ($parameters as $key) {
            if (!$this->validateRequired($key, $this->getValue($key))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that an attribute is prohibited when all other attribute not exists.
     *
     * @param array<int, string> $parameters
     */
    public function validateProhibitedWithoutAll(string $attribute, mixed $value, array $parameters): bool
    {
        foreach ($parameters as $key) {
            if ($this->validateRequired($key, $this->getValue($key))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate the size of an attribute is less than a maximum value.
     *
     * @param array{numeric} $parameters
     */
    public function validateStrlenMax(string $attribute, mixed $value, array $parameters): bool
    {
        return \is_string($value) && \mb_strlen($value, 'ASCII') <= (int) $parameters[0];
    }

    /**
     * Validate the size of an attribute is less than a maximum value.
     *
     * @param array{numeric} $parameters
     */
    public function validateStrlenMin(string $attribute, mixed $value, array $parameters): bool
    {
        return \is_string($value) && \mb_strlen($value, 'ASCII') >= (int) $parameters[0];
    }

    /**
     * Validate the size of an attribute is less than a maximum value.
     *
     * @param array{numeric} $parameters
     */
    public function validateStrlen(string $attribute, mixed $value, array $parameters): bool
    {
        return \is_string($value) && \mb_strlen($value, 'ASCII') === (int) $parameters[0];
    }

    /**
     * Validate the attribute is collection.
     */
    public function validateCollection(string $attribute, mixed $value): bool
    {
        return \is_array($value) && \array_keys($value) === \array_keys(\array_values($value));
    }

    /**
     * Replace all place-holders for the prohibited_with rule.
     *
     * @param array<int ,string> $parameters
     */
    protected function replaceProhibitedWith(string $message, string $attribute, string $rule, array $parameters): string
    {
        return \str_replace(':values', \implode(' / ', \array_map(Typer::assertString(...), $this->getAttributeList($parameters))), $message);
    }

    /**
     * Replace all place-holders for the prohibited_with_all rule.
     *
     * @param array<int ,string> $parameters
     */
    protected function replaceProhibitedWithAll(string $message, string $attribute, string $rule, array $parameters): string
    {
        return $this->replaceProhibitedWith($message, $attribute, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the prohibited_without rule.
     *
     * @param array<int ,string> $parameters
     */
    protected function replaceProhibitedWithout(string $message, string $attribute, string $rule, array $parameters): string
    {
        return $this->replaceProhibitedWith($message, $attribute, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the prohibited_without_all rule.
     *
     * @param array<int ,string> $parameters
     */
    protected function replaceProhibitedWithoutAll(string $message, string $attribute, string $rule, array $parameters): string
    {
        return $this->replaceProhibitedWith($message, $attribute, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the strlen_max rule.
     *
     * @param array{numeric} $parameters
     */
    protected function replaceStrlenMax(string $message, string $attribute, string $rule, array $parameters): string
    {
        return \str_replace(':max', (string) $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the strlen_min rule.
     *
     * @param array{numeric} $parameters
     */
    protected function replaceStrlenMin(string $message, string $attribute, string $rule, array $parameters): string
    {
        return \str_replace(':min', (string) $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the strlen rule.
     *
     * @param array{numeric} $parameters
     */
    protected function replaceStrlen(string $message, string $attribute, string $rule, array $parameters): string
    {
        return \str_replace(':size', (string) $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the collection rule.
     */
    protected function replaceCollection(string $message): string
    {
        return $message;
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    protected function convertValuesToBoolean(mixed $values): array
    {
        return \array_map(static function (mixed $value): mixed {
            return match ($value) {
                'true', '1', 1 => true,
                'false', '0', 0 => false,
                default => $value,
            };
        }, $values);
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    protected function convertValuesToNull(mixed $values): array
    {
        return \array_map(static function (mixed $value): mixed {
            return match ($value) {
                'null', '' => null,
                default => $value,
            };
        }, $values);
    }

    /**
     * @inheritDoc
     */
    protected function getAttributeType(mixed $attribute): string
    {
        if ($this->hasRule($attribute, ['collection', 'Collection'])) {
            return 'array';
        }

        return parent::getAttributeType($attribute);
    }
}
