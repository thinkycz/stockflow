<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\BankStatementTransactionCategoryEnum;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

final class BankStatementValidity
{
    /**
     * Base validity.
     */
    public BaseValidity $baseValidity;

    /**
     * Create bank statement validation rules.
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
     * Private PDF upload rule with a 10 MB limit.
     */
    public function document(): Validity
    {
        return $this->baseValidity->make()->file(10240, ['application/pdf']);
    }

    /**
     * Draft rows array.
     */
    public function transactions(): Validity
    {
        return $this->baseValidity->make()->array(null)->min(1);
    }

    /**
     * Optional existing transaction id.
     */
    public function rowId(): Validity
    {
        return $this->baseValidity->id();
    }

    /**
     * ISO date.
     */
    public function date(): Validity
    {
        return $this->baseValidity->date();
    }

    /**
     * Optional ISO date.
     */
    public function optionalDate(): Validity
    {
        return $this->baseValidity->date();
    }

    /**
     * Signed decimal amount.
     */
    public function amount(): Validity
    {
        return $this->baseValidity->make()->numeric(null, null)->decimal(0, 2);
    }

    /**
     * Transaction category.
     */
    public function category(): Validity
    {
        return $this->baseValidity->make()->inString(BankStatementTransactionCategoryEnum::values());
    }

    /**
     * Three-letter currency code.
     */
    public function currency(): Validity
    {
        return $this->baseValidity->make()->inString(['CZK']);
    }

    /**
     * Short transaction text.
     */
    public function shortText(): Validity
    {
        return $this->baseValidity->make()->varchar(160);
    }

    /**
     * Optional transaction text.
     */
    public function text(): Validity
    {
        return $this->baseValidity->make()->text();
    }
}
