<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Models\User;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class WorkerValidity
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
     * First name validation rules.
     */
    public function firstName(): Validity
    {
        return $this->baseValidity->make()->varchar(120);
    }

    /**
     * Last name validation rules.
     */
    public function lastName(): Validity
    {
        return $this->baseValidity->make()->varchar(120);
    }

    /**
     * Hourly rate validation rules (CZK, decimal with 2 places).
     */
    public function hourlyRate(): Validity
    {
        return $this->baseValidity->make()->numeric(999999, 0)->addRule('decimal', [0, 2]);
    }

    /**
     * Attendance rating enabled validation rules.
     */
    public function attendanceRatingEnabled(): Validity
    {
        return $this->baseValidity->make()->boolean();
    }

    /**
     * Optional custom calendar color in full hexadecimal notation.
     */
    public function calendarColor(): Validity
    {
        return $this->baseValidity->make()->string(7)->callback(
            static fn(mixed $value): bool => \is_string($value) && \preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1,
        );
    }

    /**
     * Id validation rules.
     */
    public function id(): Validity
    {
        return $this->baseValidity->id()->exists('workers', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Search validation rules.
     */
    public function search(): Validity
    {
        return $this->baseValidity->search();
    }
}
