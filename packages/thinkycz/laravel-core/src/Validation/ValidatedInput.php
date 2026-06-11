<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Panicker;

class ValidatedInput extends BaseInput
{
    /**
     * @inheritDoc
     *
     * @param array<mixed> $input
     */
    final public function __construct(array $input)
    {
        parent::__construct($input);
    }

    /**
     * String resolver.
     */
    public function string(string $key, string|null $default = null): string|null
    {
        $value = $this->get($key, $default);

        if ($value === null || \is_string($value)) {
            return $value;
        }

        $value = \filter_var($value);

        if ($value === false) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * Mandatory string resolver.
     */
    public function mustString(string $key, string|null $default = null): string
    {
        $value = $this->string($key, $default);

        if ($value === null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * Bool resolver.
     */
    public function bool(string $key, bool|null $default = null): bool|null
    {
        $value = $this->get($key, $default);

        if ($value === null || \is_bool($value)) {
            return $value;
        }

        $value = \filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE);

        if ($value === null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * Mandatory bool resolver.
     */
    public function mustBool(string $key, bool|null $default = null): bool
    {
        $value = $this->bool($key, $default);

        if ($value === null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * Int resolver.
     */
    public function int(string $key, int|null $default = null): int|null
    {
        $value = $this->get($key, $default);

        if ($value === null || \is_int($value)) {
            return $value;
        }

        $value = \filter_var($value, \FILTER_VALIDATE_INT);

        if ($value === false) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * Mandatory int resolver.
     */
    public function mustInt(string $key, int|null $default = null): int
    {
        $value = $this->int($key, $default);

        if ($value === null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * Float resolver.
     */
    public function float(string $key, float|null $default = null): float|null
    {
        $value = $this->get($key, $default);

        if ($value === null || \is_float($value)) {
            return $value;
        }

        $value = \filter_var($value, \FILTER_VALIDATE_FLOAT);

        if ($value === false) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * Mandatory float resolver.
     */
    public function mustFloat(string $key, float|null $default = null): float
    {
        $value = $this->float($key, $default);

        if ($value === null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * File resolver.
     */
    public function file(string $key, UploadedFile|null $default = null): UploadedFile|null
    {
        $value = $this->get($key, $default);

        if ($value === null || $value instanceof UploadedFile) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
    }

    /**
     * Mandatory file resolver.
     */
    public function mustFile(string $key, UploadedFile|null $default = null): UploadedFile
    {
        $value = $this->file($key, $default);

        if ($value === null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * Array resolver.
     *
     * @param array<mixed>|null $default
     *
     * @return array<mixed>|null
     */
    public function array(string $key, array|null $default = null): array|null
    {
        $value = $this->get($key, $default);

        if ($value === null || \is_array($value)) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
    }

    /**
     * Mandatory array resolver.
     *
     * @param array<mixed>|null $default
     *
     * @return array<mixed>
     */
    public function mustArray(string $key, array|null $default): array
    {
        $value = $this->array($key, $default);

        if ($value === null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * Date resolver.
     */
    public function date(string $key, Carbon|null $default = null, string|null $format = null, string|null $tz = null): Carbon|null
    {
        $value = $this->get($key, $default);

        if ($value === null || $value instanceof Carbon) {
            return $value;
        }

        $value = \filter_var($value);

        if ($value === false || $value === '') {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        if ($format === null) {
            return Carbon::parse($value, $tz)->setTimezone(Config::inject()->appTimezone());
        }

        $value = Carbon::createFromFormat($format, $value, $tz);

        if ($value === null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value->setTimezone(Config::inject()->appTimezone());
    }

    /**
     * Mandatory date resolver.
     */
    public function mustDate(string $key, Carbon|null $default = null, string|null $format = null, string|null $tz = null): Carbon
    {
        $value = $this->date($key, $default, $format, $tz);

        if ($value === null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * Make new validated input from given data.
     *
     * @param array<mixed> $data
     */
    public function newValidatedInput(array $data): static
    {
        return new static($data);
    }

    /**
     * Make new validated input from given key.
     *
     * @param array<mixed>|null $default
     */
    public function validatedInput(string $key, array|null $default): static
    {
        return new static($this->mustArray($key, $default));
    }

    /**
     * Make new validated inputs from given key.
     *
     * @param array<mixed>|null $default
     *
     * @return array<int, static>
     */
    public function validatedInputs(string $key, array|null $default): array
    {
        $validatedInputs = [];

        $data = $this->mustArray($key, $default);

        foreach ($data as $validatedInput) {
            if (!\is_array($validatedInput)) {
                $validatedInputs[] = new static(['value' => $validatedInput]);
            } else {
                $validatedInputs[] = new static($validatedInput);
            }
        }

        return $validatedInputs;
    }

    /**
     * String or int resolver.
     */
    public function stringOrInt(string $key, int|string|null $default = null): int|string|null
    {
        $value = $this->get($key, $default);

        if ($value === null || \is_string($value) || \is_int($value)) {
            return $value;
        }

        $filtered = \filter_var($value, \FILTER_VALIDATE_INT);

        if ($filtered !== false) {
            return $filtered;
        }

        $filtered = \filter_var($value);

        if ($filtered === false) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $filtered;
    }

    /**
     * Mandatory string or int resolver.
     */
    public function mustStringOrInt(string $key, int|string|null $default = null): int|string
    {
        $value = $this->stringOrInt($key, $default);

        if ($value === null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('key', 'value', 'default'));
        }

        return $value;
    }

    /**
     * Int or string resolver.
     */
    public function intOrString(string $key, int|string|null $default = null): int|string|null
    {
        return $this->stringOrInt($key, $default);
    }

    /**
     * Mandatory int or string resolver..
     */
    public function mustIntOrString(string $key, int|string|null $default = null): int|string
    {
        return $this->mustStringOrInt($key, $default);
    }
}
