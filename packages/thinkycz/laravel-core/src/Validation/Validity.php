<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation;

use Illuminate\Contracts\Support\Arrayable as ArrayableContract;
use Thinkycz\LaravelCore\Validation\Concerns\HasValidityControlRules;
use Thinkycz\LaravelCore\Validation\Concerns\HasValidityDatabaseRules;
use Thinkycz\LaravelCore\Validation\Concerns\HasValidityDomainRules;
use Thinkycz\LaravelCore\Validation\Concerns\HasValidityFileRules;
use Thinkycz\LaravelCore\Validation\Concerns\HasValidityLimits;
use Thinkycz\LaravelCore\Validation\Concerns\HasValidityNumericRules;
use Thinkycz\LaravelCore\Validation\Concerns\HasValidityPresenceRules;
use Thinkycz\LaravelCore\Validation\Concerns\HasValiditySerialization;
use Thinkycz\LaravelCore\Validation\Concerns\HasValidityState;
use Thinkycz\LaravelCore\Validation\Concerns\HasValidityStringRules;
use Thinkycz\LaravelCore\Validation\Concerns\HasValidityTypeRules;

/**
 * @implements ArrayableContract<int, mixed>
 */
class Validity implements ArrayableContract
{
    use HasValidityControlRules;
    use HasValidityDatabaseRules;
    use HasValidityDomainRules;
    use HasValidityFileRules;
    use HasValidityLimits;
    use HasValidityNumericRules;
    use HasValidityPresenceRules;
    use HasValiditySerialization;
    use HasValidityState;
    use HasValidityStringRules;
    use HasValidityTypeRules;

    /**
     * Constructor.
     */
    protected function __construct() {}

    /**
     * Create a new validity instance.
     */
    public static function make(): self
    {
        return new self();
    }
}
