<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class ShiftRequestValidity
{
    /**
     * Base validity builder.
     */
    public BaseValidity $baseValidity;

    /**
     * Create validation scoped to one company.
     */
    public function __construct(private readonly int $userId)
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Inject a scoped validity instance.
     */
    public static function inject(int $userId): self { return new self($userId); }

    /**
     * Validate a worker belonging to the company.
     */
    public function workerId(): Validity
    {
        return $this->baseValidity->id()->exists('workers', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Validate a request date.
     */
    public function date(): Validity { return $this->baseValidity->date(); }

    /**
     * Validate a quarter-hour start time.
     */
    public function startTime(): Validity
    {
        return $this->baseValidity->make()->string(null)->dateFormat('H:i')->in(ShiftValidity::timeOptions());
    }

    /**
     * Validate a later quarter-hour end time.
     */
    public function endTime(): Validity
    {
        return $this->baseValidity->make()->string(null)->dateFormat('H:i')->in(ShiftValidity::timeOptions())->after('start_time');
    }

    /**
     * Validate a supported year.
     */
    public function year(): Validity { return $this->baseValidity->make()->integer(2100, 2000); }

    /**
     * Validate a calendar month.
     */
    public function month(): Validity { return $this->baseValidity->make()->integer(12, 1); }

    /**
     * Validate the requested lock state.
     */
    public function locked(): Validity { return $this->baseValidity->make()->boolean(); }
}
