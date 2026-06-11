<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation;

use Thinkycz\LaravelCore\Support\Config;

class AuthValidity
{
    /**
     * Allowed locales.
     *
     * @var ?array<int, string>
     */
    public static array|null $allowedLocales = null;

    /**
     * Base validity.
     */
    public BaseValidity $baseValidity;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Inject.
     */
    public static function inject(): self
    {
        return new self();
    }

    /**
     * Remember validation rules.
     */
    public function remember(): Validity
    {
        return $this->baseValidity->make()->boolean();
    }

    /**
     * Name validation rules.
     */
    public function name(): Validity
    {
        return $this->baseValidity->make()->varchar();
    }

    /**
     * Email validation rules.
     */
    public function email(): Validity
    {
        return $this->baseValidity->make()->varchar()->email();
    }

    /**
     * Password validation rules.
     */
    public function password(): Validity
    {
        return $this->baseValidity->make()->string(1024)->password();
    }

    /**
     * Password reset token validation rules.
     */
    public function passwordResetToken(): Validity
    {
        return $this->baseValidity->make()->varchar();
    }

    /**
     * Email verification token validation rules.
     */
    public function emailVerificationToken(): Validity
    {
        return $this->baseValidity->make()->varchar();
    }

    /**
     * Locale validation rules.
     */
    public function locale(): Validity
    {
        return $this->baseValidity->make()->inString(static::$allowedLocales ?? Config::inject()->assertArray('app.locales'));
    }

    /**
     * Id validation rules.
     */
    public function id(): Validity
    {
        return $this->baseValidity->id();
    }

    /**
     * Slug validation rules.
     */
    public function slug(): Validity
    {
        return $this->baseValidity->slug();
    }

    /**
     * E-mail verified at validation rules.
     */
    public function emailVerifiedAt(): Validity
    {
        return $this->baseValidity->dateTime();
    }

    /**
     * Remember token validation rules.
     */
    public function rememberToken(): Validity
    {
        return $this->baseValidity->make()->varchar(100);
    }

    /**
     * Created at validation rules.
     */
    public function createdAt(): Validity
    {
        return $this->baseValidity->dateTime();
    }

    /**
     * Updated at validation rules.
     */
    public function updatedAt(): Validity
    {
        return $this->baseValidity->dateTime();
    }
}
