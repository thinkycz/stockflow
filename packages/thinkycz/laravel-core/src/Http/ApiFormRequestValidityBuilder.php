<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Http;

use Illuminate\Support\Arr;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class ApiFormRequestValidityBuilder
{
    /**
     * Validation rules.
     *
     * @var array<string, mixed>
     */
    protected array $rules = [];

    /**
     * Base validity.
     */
    protected BaseValidity $baseValidity;

    /**
     * Constructor.
     */
    public function __construct(
        protected ApiFormRequest $request,
        BaseValidity|null $baseValidity = null,
    ) {
        $this->baseValidity = $baseValidity ?? new BaseValidity();
    }

    /**
     * Add custom validation rules.
     *
     * @param array<string, mixed> $rules
     */
    public function rules(array $rules): static
    {
        return $this->add($rules);
    }

    /**
     * Add base filter validation rules.
     */
    public function filter(): static
    {
        return $this->add([
            'filter' => $this->baseValidity->filter()->nullable()->filled(),
        ]);
    }

    /**
     * Add ID filter validation rules.
     */
    public function filterId(): static
    {
        return $this->filter()->add([
            'filter.id' => $this->baseValidity->collection()->nullable()->filled(),
            'filter.id.*' => $this->baseValidity->id()->required()->distinct(),
        ]);
    }

    /**
     * Add not ID filter validation rules.
     */
    public function filterNotId(): static
    {
        return $this->filter()->add([
            'filter.not_id' => $this->baseValidity->collection()->nullable()->filled(),
            'filter.not_id.*' => $this->baseValidity->id()->required()->distinct(),
        ]);
    }

    /**
     * Add slug filter validation rules.
     */
    public function filterSlug(): static
    {
        return $this->filter()->add([
            'filter.slug' => $this->baseValidity->collection()->nullable()->filled(),
            'filter.slug.*' => $this->baseValidity->slug()->required()->distinct(),
        ]);
    }

    /**
     * Add not slug filter validation rules.
     */
    public function filterNotSlug(): static
    {
        return $this->filter()->add([
            'filter.not_slug' => $this->baseValidity->collection()->nullable()->filled(),
            'filter.not_slug.*' => $this->baseValidity->slug()->required()->distinct(),
        ]);
    }

    /**
     * Add search filter validation rules.
     */
    public function filterSearch(): static
    {
        return $this->filter()->add([
            'filter.search' => $this->baseValidity->search()->nullable()->filled(),
        ]);
    }

    /**
     * Add take validation rules.
     */
    public function take(int $max = \PHP_INT_MAX): static
    {
        return $this->add([
            'take' => $this->baseValidity->take($max)->nullable()->filled(),
        ]);
    }

    /**
     * Add mode validation rules.
     *
     * @param array<string> $modes
     */
    public function mode(array $modes): static
    {
        return $this->add([
            'mode' => $this->baseValidity->mode($modes)->nullable()->filled(),
        ]);
    }

    /**
     * Add guard validation rules.
     *
     * @param array<string> $guards
     */
    public function guard(array $guards): static
    {
        return $this->add([
            'guard' => $this->baseValidity->make()->inString($guards)->nullable(),
        ]);
    }

    /**
     * Add sort validation rules.
     *
     * @param array<string> $sort
     */
    public function sort(array $sort): static
    {
        return $this->add([
            'sort' => $this->baseValidity->collection()->nullable()->filled(),
            'sort.*' => $this->baseValidity->sort($sort)->required()->distinct(),
        ]);
    }

    /**
     * Add page validation rules.
     */
    public function page(): static
    {
        return $this->add([
            'page' => $this->baseValidity->page()->nullable()->filled(),
        ]);
    }

    /**
     * Add signed URL validation rules.
     */
    public function signed(): static
    {
        return $this->add([
            'signature' => $this->baseValidity->signature()
                ->nullable()
                ->filled()
                ->requiredWith(['expires']),
            'expires' => $this->baseValidity->expires()->nullable()->filled(),
        ]);
    }

    /**
     * Add cursor validation rules.
     */
    public function cursor(): static
    {
        return $this->add([
            'cursor' => $this->baseValidity->cursor()->nullable()->filled(),
        ]);
    }

    /**
     * Add JSON:API query parameter validation rules.
     */
    public function jsonApi(): static
    {
        return $this->add([
            'include' => $this->baseValidity->make()->varchar()->nullable()->filled(),
            'fields' => $this->baseValidity->array()->nullable()->filled(),
            'fields.*' => $this->baseValidity->make()->varchar()->required(),
        ]);
    }

    /**
     * Validate the request with accumulated rules.
     */
    public function validate(): Parser
    {
        return $this->request->validate($this->validationRules());
    }

    /**
     * Validate only the accumulated rules for staged controller validation.
     */
    public function partialValidate(): Parser
    {
        return new Parser($this->request->validatorFactory()->make(
            Arr::only($this->request->all(), $this->partialInputKeys()),
            $this->validationRules(),
        )->validate());
    }

    /**
     * Get accumulated validation rules.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rules;
    }

    /**
     * Get normalized Laravel validation rules.
     *
     * @return array<string, mixed>
     */
    protected function validationRules(): array
    {
        $rules = [];

        foreach ($this->rules as $field => $rule) {
            $rules[$field] = $this->normalizeRule($rule);
        }

        return $rules;
    }

    /**
     * Normalize a builder rule value.
     */
    protected function normalizeRule(mixed $rule): mixed
    {
        if ($rule instanceof Validity) {
            return $rule->toArray();
        }

        return $rule;
    }

    /**
     * Get request input keys addressed by accumulated rules.
     *
     * @return array<int, string>
     */
    protected function partialInputKeys(): array
    {
        $keys = [];

        foreach (\array_keys($this->rules) as $key) {
            $length = \strcspn($key, '.*');
            $keys[] = $length > 0 ? \mb_substr($key, 0, $length) : $key;
        }

        return \array_values(\array_unique($keys));
    }

    /**
     * Add validation rules.
     *
     * @param array<string, mixed> $rules
     */
    protected function add(array $rules): static
    {
        $this->rules = \array_replace($this->rules, $rules);

        return $this;
    }
}
