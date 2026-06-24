<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Models\User;
use Thinkycz\LaravelCore\Validation\AuthValidity;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class UserValidity
{
    /**
     * Base validity.
     */
    public BaseValidity $baseValidity;

    /**
     * Auth validity (for shared email/password rules).
     */
    public AuthValidity $authValidity;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly int|null $adminId = null,
    ) {
        $this->baseValidity = new BaseValidity();
        $this->authValidity = new AuthValidity();
    }

    /**
     * Inject.
     */
    public static function inject(int|null $adminId = null): self
    {
        return new self($adminId ?? User::mustAuth()->getKey());
    }

    /**
     * E-mail validation rules. Use `$ignoreId` on update to skip the unique
     * check for the record being edited.
     */
    public function email(int|null $ignoreId = null): Validity
    {
        $rule = $this->authValidity->email();

        return $ignoreId === null
            ? $rule->unique('users', 'email')
            : $rule->unique('users', 'email', $ignoreId);
    }

    /**
     * Password validation rules (min 8 chars, max 1024).
     */
    public function password(): Validity
    {
        return $this->authValidity->password();
    }

    /**
     * Assigned store id validation rules (store owned by the admin).
     */
    public function assignedStoreId(): Validity
    {
        return $this->baseValidity->id()->exists('stores', 'id', ['user_id', (string) $this->adminId]);
    }
}
