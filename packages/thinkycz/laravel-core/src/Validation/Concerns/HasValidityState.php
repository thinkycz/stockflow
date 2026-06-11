<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Concerns;

trait HasValidityState
{
    /**
     * Bail flag.
     */
    protected bool $bail = true;

    /**
     * Sometimes flag.
     */
    protected bool $sometimes = false;

    /**
     * Nullable flag.
     */
    protected bool $nullable = false;

    /**
     * Required flag.
     */
    protected bool $required = false;

    /**
     * Filled flag.
     */
    protected bool $filled = false;

    /**
     * Missing flag.
     */
    protected bool $missing = false;

    /**
     * Prohibited flag.
     */
    protected bool $prohibited = false;

    /**
     * Array flag.
     */
    protected bool $array = false;

    /**
     * Collection flag.
     */
    protected bool $collection = false;

    /**
     * Boolean flag.
     */
    protected bool $boolean = false;

    /**
     * File flag.
     */
    protected bool $file = false;

    /**
     * Integer flag.
     */
    protected bool $integer = false;

    /**
     * Numeric flag.
     */
    protected bool $numeric = false;

    /**
     * String flag.
     */
    protected bool $string = false;

    /**
     * Rules.
     *
     * @var array<int, mixed>
     */
    protected array $rules = [];

    /**
     * Skip next addRule class.
     */
    protected bool $skipNext = false;

    /**
     * Unsafe.
     */
    protected bool $unsafe = false;
}
