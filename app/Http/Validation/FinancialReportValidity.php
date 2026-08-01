<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\FinancialDirectionEnum;
use App\Enums\FinancialSourceTypeEnum;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class FinancialReportValidity
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
    public static function inject(): self { return new self(); }

    /**
     * Validate a report year.
     */
    public function year(): Validity { return $this->baseValidity->make()->integer(2100, 2000); }

    /**
     * Validate a report month.
     */
    public function month(): Validity { return $this->baseValidity->make()->integer(12, 1); }

    /**
     * Validate a financial direction.
     */
    public function direction(): Validity { return $this->baseValidity->make()->inString(FinancialDirectionEnum::values()); }

    /**
     * Validate an automatic source type.
     */
    public function sourceType(): Validity { return $this->baseValidity->make()->inString(FinancialSourceTypeEnum::values()); }

    /**
     * Validate an automatic source key.
     */
    public function sourceKey(): Validity { return $this->baseValidity->make()->varchar(120); }

    /**
     * Validate a row label.
     */
    public function label(): Validity { return $this->baseValidity->make()->varchar(160); }

    /**
     * Validate a row date.
     */
    public function occurredOn(): Validity { return $this->baseValidity->make()->string(null)->dateFormat(); }

    /**
     * Validate a non-negative CZK amount.
     */
    public function amount(): Validity { return $this->baseValidity->make()->numeric(999999999999.99, 0)->addRule('decimal', [0, 2]); }

    /**
     * Validate an optional note.
     */
    public function note(): Validity { return $this->baseValidity->make()->text(2000); }
}
