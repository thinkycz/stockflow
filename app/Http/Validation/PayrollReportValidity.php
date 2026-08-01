<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\PayrollAdjustmentTypeEnum;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class PayrollReportValidity
{
    /**
     * Base validity builder.
     */
    public BaseValidity $baseValidity;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Inject a validity instance.
     */
    public static function inject(): self
    {
        return new self();
    }

    /**
     * Report year rules.
     */
    public function year(): Validity
    {
        return $this->baseValidity->make()->integer(2100, 2000);
    }

    /**
     * Report month rules.
     */
    public function month(): Validity
    {
        return $this->baseValidity->make()->integer(12, 1);
    }

    /**
     * Worker id rules.
     */
    public function workerId(): Validity
    {
        return $this->baseValidity->id()->exists('workers', 'id');
    }

    /**
     * Adjustment type rules.
     */
    public function type(): Validity
    {
        return $this->baseValidity->make()->inString(PayrollAdjustmentTypeEnum::values());
    }

    /**
     * Positive CZK amount rules.
     */
    public function amount(): Validity
    {
        return $this->baseValidity->make()->numeric(999999999999.99, 0.01)->addRule('decimal', [0, 2]);
    }

    /**
     * Required reason rules.
     */
    public function reason(): Validity
    {
        return $this->baseValidity->make()->varchar(255);
    }
}
