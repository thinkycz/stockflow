<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Concerns;

use Thinkycz\LaravelCore\Support\Csv;
use Thinkycz\LaravelCore\Support\Typer;

trait HasValiditySerialization
{
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        Typer::assert(
            $this->unsafe || $this->array || $this->collection || $this->boolean || $this->file || $this->integer || $this->numeric || $this->string || $this->prohibited || $this->missing,
            'attribute must be validated against base type (array|object|collection|boolean|file|integer|numeric|string)',
        );
        Typer::assert($this->unsafe || $this->required || $this->nullable || $this->missing || $this->prohibited, 'attribute must be validated against nullable or required');

        $rules = [];

        if ($this->sometimes) {
            $rules[] = 'sometimes';
        }

        if ($this->bail) {
            $rules[] = 'bail';
        }

        if ($this->missing) {
            $rules[] = 'missing';
        }

        if ($this->prohibited) {
            $rules[] = 'prohibited';
        }

        if ($this->nullable) {
            $rules[] = 'nullable';
        }

        if ($this->required) {
            $rules[] = 'required';
        }

        if ($this->filled) {
            $rules[] = 'filled';
        }

        if ($this->array) {
            $rules[] = 'array';
        }

        if ($this->collection) {
            $rules[] = 'collection';
        }

        if ($this->boolean) {
            $rules[] = 'boolean';
        }

        if ($this->file) {
            $rules[] = 'file';
        }

        if ($this->integer) {
            $rules[] = 'integer';
        }

        if ($this->numeric) {
            $rules[] = 'numeric';
        }

        if ($this->string) {
            $rules[] = 'string';
        }

        return \array_merge($rules, $this->rules);
    }

    /**
     * Add new rule.
     *
     * @param ?array<int, mixed> $arguments
     *
     * @return $this
     */
    public function addRule(mixed $rule, array|null $arguments = null): static
    {
        Typer::assert($this->skipNext === false);

        if (\is_string($rule)) {
            if ($arguments !== null && \count($arguments) > 0) {
                $rule = $rule . (\str_contains($rule, ':') ? ',' : ':') . $this->formatArguments($arguments);
            }
        }

        if (!\in_array($rule, $this->rules, true)) {
            $this->rules[] = $rule;
        }

        return $this;
    }

    /**
     * Format arguments.
     *
     * @param array<int, mixed> $arguments
     */
    protected function formatArguments(array $arguments): string
    {
        return Csv::line($arguments);
    }
}
