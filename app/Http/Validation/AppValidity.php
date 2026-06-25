<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Models\User;
use Thinkycz\LaravelCore\Validation\BaseValidity;

/**
 * @phpstan-consistent-constructor
 */
abstract class AppValidity
{
    public BaseValidity $baseValidity;

    public function __construct(
        protected readonly int|null $userId = null,
    ) {
        $this->baseValidity = new BaseValidity();
    }

    public static function inject(int|null $userId = null): static
    {
        return new static($userId ?? User::mustAuth()->getKey());
    }
}
