<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

use Illuminate\Validation\Validator;
use Thinkycz\LaravelCore\Exceptions\ValidationException;

class Thrower
{
    /**
     * Exception status.
     */
    public int $status = 422;

    /**
     * Exception code.
     */
    public int $code = 0;

    /**
     * Exception headers.
     *
     * @var array<mixed>
     */
    public array $headers = [];

    /**
     * Exception data.
     *
     * @var array<mixed>
     */
    public array $data = [];

    /**
     * Exception message.
     */
    public string $message = '';

    /**
     * Constructor.
     */
    public function __construct(public Validator $validator) {}

    /**
     * Default constructor.
     */
    public static function default(): self
    {
        return new self(Resolver::resolveValidator());
    }

    /**
     * Throw validation exception.
     */
    public function throw(): never
    {
        throw new ValidationException($this->validator, $this->status, $this->message, null, $this->headers, $this->code, $this->data);
    }

    /**
     * Status setter.
     *
     * @return $this
     */
    public function setStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Code setter.
     *
     * @return $this
     */
    public function setCode(int $code): static
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Headers setter.
     *
     * @param array<mixed> $headers
     *
     * @return $this
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Message setter.
     *
     * @return $this
     */
    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Data setter.
     *
     * @param array<mixed> $data
     *
     * @return $this
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Merge data.
     *
     * @param array<mixed> $data
     *
     * @return $this
     */
    public function mergeData(array $data): static
    {
        $this->data = \array_replace($this->data, $data);

        return $this;
    }

    /**
     * Merge headers.
     *
     * @param array<mixed> $headers
     *
     * @return $this
     */
    public function mergeHeaders(array $headers): static
    {
        $this->headers = \array_replace($this->headers, $headers);

        return $this;
    }

    /**
     * Add message to validator.
     *
     * @return $this
     */
    public function message(string $attribute, string $message): static
    {
        $this->validator->errors()->add($attribute, $message);

        return $this;
    }

    /**
     * Add messages to validator.
     *
     * @param array<string> $attributes
     *
     * @return $this
     */
    public function messages(array $attributes, string $message): static
    {
        foreach ($attributes as $attribute) {
            $this->message($attribute, $message);
        }

        return $this;
    }

    /**
     * Add error to validator.
     *
     * @param array<array-key, string> $params
     *
     * @return $this
     */
    public function error(string $attribute, string $error, array $params = []): static
    {
        $this->validator->addFailure($attribute, $error, $params);

        return $this;
    }

    /**
     * Add errors to validator.
     *
     * @param array<string> $attributes
     * @param array<array-key, string> $params
     *
     * @return $this
     */
    public function errors(array $attributes, string $error, array $params = []): static
    {
        foreach ($attributes as $attribute) {
            $this->error($attribute, $error, $params);
        }

        return $this;
    }

    /**
     * Add throttle errors.
     *
     * @param array<string> $attributes
     *
     * @return $this
     */
    public function throttled(array $attributes, int $seconds): static
    {
        return $this->setStatus(429)
            ->mergeHeaders([
                'Retry-After' => (string) $seconds,
            ])
            ->errors($attributes, 'Throttled', [
                'seconds' => (string) $seconds,
                'minutes' => (string) \ceil($seconds / 60),
            ]);
    }

    /**
     * Add unique errors.
     *
     * @param array<string> $attributes
     *
     * @return $this
     */
    public function unique(array $attributes): static
    {
        return $this->errors($attributes, 'Unique');
    }

    /**
     * Add exists errors.
     *
     * @param array<string> $attributes
     *
     * @return $this
     */
    public function exists(array $attributes): static
    {
        return $this->errors($attributes, 'Exists');
    }

    /**
     * Add invalid errors.
     *
     * @param array<string> $attributes
     *
     * @return $this
     */
    public function invalid(array $attributes): static
    {
        return $this->errors($attributes, 'Invalid');
    }
}
