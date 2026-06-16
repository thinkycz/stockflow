<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Models\User;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class StatementValidity
{
    /**
     * Base validity.
     */
    public BaseValidity $baseValidity;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly int|null $userId = null,
    ) {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Inject.
     */
    public static function inject(int|null $userId = null): self
    {
        return new self($userId ?? User::mustAuth()->getKey());
    }

    /**
     * Store id validation rules (any owned store).
     */
    public function storeId(): Validity
    {
        return $this->baseValidity->id()->exists('stores', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Year validation rules.
     */
    public function year(): Validity
    {
        return $this->baseValidity->make()->unsignedSmallInt(2100, 2000);
    }

    /**
     * Month validation rules.
     */
    public function month(): Validity
    {
        return $this->baseValidity->make()->unsignedTinyInt(12, 1);
    }

    /**
     * Statement id validation rules.
     */
    public function id(): Validity
    {
        return $this->baseValidity->id()->exists('statements', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Days array validation rules.
     */
    public function days(): Validity
    {
        return $this->baseValidity->make()->array(null)->min(1);
    }

    /**
     * Per-day id validation rules.
     */
    public function dayId(): Validity
    {
        return $this->baseValidity->id()->exists('statement_days', 'id', []);
    }

    /**
     * Per-day date validation rules.
     */
    public function dayDate(): Validity
    {
        return $this->baseValidity->date();
    }

    /**
     * Money amount validation rules (numeric, 0..9999999.99).
     */
    public function amount(): Validity
    {
        return $this->baseValidity->make()->numeric(null, 0)->decimal(0, 2);
    }
}
