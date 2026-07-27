<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Models\User;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class ShiftValidity
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
     * Valid quarter-hour time values.
     *
     * @return list<string>
     */
    public static function timeOptions(): array
    {
        $times = [];

        for ($hour = 0; $hour < 24; ++$hour) {
            foreach ([0, 15, 30, 45] as $minute) {
                $times[] = \sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return $times;
    }

    /**
     * Worker id validation rules.
     */
    public function workerId(): Validity
    {
        return $this->baseValidity->id()->exists('workers', 'id', ['user_id', (string) $this->userId]);
    }

    /**
     * Shift preset id validation rules for an active store.
     */
    public function presetId(int $storeId): Validity
    {
        return $this->baseValidity->id()->exists('shift_presets', 'id', [
            'user_id',
            (string) $this->userId,
            'store_id',
            (string) $storeId,
        ]);
    }

    /**
     * Optional explicit overlap override.
     */
    public function allowOverlap(): Validity
    {
        return $this->baseValidity->make()->boolean();
    }

    /**
     * Date validation rules.
     */
    public function date(): Validity
    {
        return $this->baseValidity->date();
    }

    /**
     * Start time validation rules (H:i format, quarter-hour steps).
     */
    public function startTime(): Validity
    {
        return $this->baseValidity->make()
            ->string(null)
            ->dateFormat('H:i')
            ->in(self::timeOptions());
    }

    /**
     * End time validation rules (H:i format, quarter-hour steps).
     */
    public function endTime(): Validity
    {
        return $this->baseValidity->make()
            ->string(null)
            ->dateFormat('H:i')
            ->in(self::timeOptions())
            ->after('start_time');
    }

    /**
     * Id validation rules.
     */
    public function id(): Validity
    {
        return $this->baseValidity->id()->exists('shifts', 'id', ['user_id', (string) $this->userId]);
    }
}
