<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

class AllInput extends BaseInput
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
            return null;
        }

        return $value;
    }

    /**
     * Mandatory string resolver.
     */
    public function mustString(string $key, string|null $default = null): string
    {
        return $this->string($key, $default) ?? '';
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

        return \filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE);
    }

    /**
     * Mandatory bool resolver.
     */
    public function mustBool(string $key, bool|null $default = null): bool
    {
        return $this->bool($key, $default) ?? false;
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
            return null;
        }

        return $value;
    }

    /**
     * Mandatory int resolver.
     */
    public function mustInt(string $key, int|null $default = null): int
    {
        return $this->int($key, $default) ?? 0;
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
            return null;
        }

        return $value;
    }

    /**
     * Mandatory float resolver.
     */
    public function mustFloat(string $key, float|null $default = null): float
    {
        return $this->float($key, $default) ?? 0.0;
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

        return null;
    }

    /**
     * Mandatory file resolver.
     */
    public function mustFile(string $key, UploadedFile|null $default = null): UploadedFile
    {
        return $this->file($key, $default) ?? UploadedFile::createFromBase(UploadedFile::fake()->create(Typer::assertString(\tempnam(\sys_get_temp_dir(), 'php'))));
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

        return null;
    }

    /**
     * Mandatory array resolver.
     *
     * @param array<mixed>|null $default
     *
     * @return array<mixed>
     */
    public function mustArray(string $key, array|null $default = null): array
    {
        return $this->array($key, $default) ?? [];
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
            return null;
        }

        if ($format === null) {
            return Carbon::parse($value, $tz)->setTimezone(Config::inject()->appTimezone());
        }

        $value = Carbon::createFromFormat($format, $value, $tz);

        return $value?->setTimezone(Config::inject()->appTimezone());
    }

    /**
     * Mandatory date resolver.
     */
    public function mustDate(string $key, Carbon|null $default = null, string|null $format = null, string|null $tz = null): Carbon
    {
        return $this->date($key, $default, $format, $tz) ?? Carbon::now();
    }

    /**
     * Make new all input from given data.
     *
     * @param array<mixed> $data
     */
    public function newAllInput(array $data): static
    {
        return new static($data);
    }

    /**
     * Make new all input from given key.
     *
     * @param array<mixed>|null $default
     */
    public function allInput(string $key, array|null $default = null): static
    {
        return new static($this->mustArray($key, $default));
    }

    /**
     * Make new all inputs from given key.
     *
     * @param array<mixed>|null $default
     *
     * @return array<int, static>
     */
    public function allInputs(string $key, array|null $default = null): array
    {
        $allInputs = [];

        $data = $this->mustArray($key, $default);

        foreach ($data as $allInput) {
            if (!\is_array($allInput)) {
                $allInputs[] = new static(['value' => $allInput]);
            } else {
                $allInputs[] = new static($allInput);
            }
        }

        return $allInputs;
    }
}
