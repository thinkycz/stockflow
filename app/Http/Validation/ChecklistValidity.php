<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\ChecklistShiftEnum;
use App\Enums\ChecklistTemplateScopeEnum;
use App\Models\User;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class ChecklistValidity
{
    /**
     * Core validity builder.
     */
    public BaseValidity $baseValidity;

    /**
     * Create checklist validation rules for one company owner.
     */
    public function __construct(private readonly int $userId)
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Resolve checklist validity for the authenticated scope owner.
     */
    public static function inject(int|null $userId = null): self
    {
        return new self($userId ?? User::mustAuth()->resolveScopeUser()->getKey());
    }

    /**
     * Template recurrence scope.
     */
    public function scope(): Validity { return $this->baseValidity->make()->string(16)->in(ChecklistTemplateScopeEnum::values()); }

    /**
     * ISO weekday.
     */
    public function weekday(): Validity { return $this->baseValidity->make()->integer(7, 1); }

    /**
     * Shift key.
     */
    public function shift(): Validity { return $this->baseValidity->make()->string(16)->in(ChecklistShiftEnum::values()); }

    /**
     * Ordered task rows.
     */
    public function tasks(): Validity { return $this->baseValidity->make()->array(null)->max(100); }

    /**
     * Individual task text.
     */
    public function taskText(): Validity { return $this->baseValidity->make()->string(500); }

    /**
     * Target completion state.
     */
    public function completed(): Validity { return $this->baseValidity->make()->boolean(); }

    /**
     * Company worker id.
     */
    public function workerId(): Validity { return $this->baseValidity->id()->exists('workers', 'id', ['user_id', (string) $this->userId]); }

    /**
     * Item lock version.
     */
    public function lockVersion(): Validity { return $this->baseValidity->make()->integer(null, 1); }

    /**
     * Administrative audit reason.
     */
    public function reason(): Validity { return $this->baseValidity->make()->text(2000); }
}
