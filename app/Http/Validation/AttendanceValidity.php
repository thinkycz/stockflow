<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\AttendanceActionEnum;
use App\Models\User;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class AttendanceValidity
{
    /**
     * Base validity builder.
     */
    public BaseValidity $baseValidity;

    /**
     * Constructor.
     */
    public function __construct(private readonly int|null $userId = null)
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Inject a validity instance for the data owner.
     */
    public static function inject(int|null $userId = null): self
    {
        return new self($userId ?? User::mustAuth()->resolveScopeUser()->getKey());
    }

    /**
     * Validate a worker owned by the company admin.
     */
    public function workerId(): Validity { return $this->baseValidity->id()->exists('workers', 'id', ['user_id', (string) $this->userId]); }

    /**
     * Validate an attendance state action.
     */
    public function action(): Validity { return $this->baseValidity->make()->inString(AttendanceActionEnum::values()); }

    /**
     * Validate the out-of-shift confirmation flag.
     */
    public function confirmation(): Validity { return $this->baseValidity->make()->boolean(); }

    /**
     * Validate a browser-local date and time.
     */
    public function localDateTime(): Validity { return $this->baseValidity->make()->string(null)->dateFormat('Y-m-d\\TH:i'); }

    /**
     * Validate the correction break collection.
     */
    public function breaks(): Validity { return $this->baseValidity->make()->array(null); }

    /**
     * Validate a correction reason.
     */
    public function reason(): Validity { return $this->baseValidity->make()->text(); }
}
