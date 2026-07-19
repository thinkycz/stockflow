<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class ShiftPresetValidity
{
    /**
     * Shared validation rule builder.
     */
    public BaseValidity $baseValidity;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly int $storeId,
        private readonly int|null $presetId = null,
    ) {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Build validation rules for a store and optional existing preset.
     */
    public static function inject(int $storeId, int|null $presetId = null): self
    {
        return new self($storeId, $presetId);
    }

    /**
     * Store-unique preset name rules.
     */
    public function name(): Validity
    {
        return $this->baseValidity->make()
            ->string(100)
            ->unique('shift_presets', 'name', $this->presetId, 'id', ['store_id', (string) $this->storeId]);
    }

    /**
     * Quarter-hour start time rules.
     */
    public function startTime(): Validity
    {
        return $this->baseValidity->make()
            ->string(null)
            ->dateFormat('H:i')
            ->in(ShiftValidity::timeOptions());
    }

    /**
     * Same-day end time rules.
     */
    public function endTime(): Validity
    {
        return $this->baseValidity->make()
            ->string(null)
            ->dateFormat('H:i')
            ->in(ShiftValidity::timeOptions())
            ->after('start_time');
    }
}
