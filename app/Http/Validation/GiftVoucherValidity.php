<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class GiftVoucherValidity
{
    /**
     * Base validity builder.
     */
    public BaseValidity $baseValidity;

    /**
     * Create a voucher validity helper.
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
     * Human-entered voucher code.
     */
    public function code(): Validity
    {
        return $this->baseValidity->make()->string(32);
    }

    /**
     * Redemption confirmation ticket.
     */
    public function ticket(): Validity
    {
        return $this->baseValidity->make()->string(64);
    }

    /**
     * Batch quantity.
     */
    public function quantity(): Validity
    {
        return $this->baseValidity->make()->integer(100, 1);
    }

    /**
     * Voucher value in CZK.
     */
    public function amount(): Validity
    {
        return $this->baseValidity->make()->numeric(999999.99, 0.01)->addRule('decimal', [0, 2]);
    }

    /**
     * Optional local expiration date.
     */
    public function expiresOn(): Validity
    {
        return $this->baseValidity->date()->addRule('after_or_equal', ['today']);
    }

    /**
     * Required admin audit reason.
     */
    public function reason(): Validity
    {
        return $this->baseValidity->make()->string(500);
    }

    /**
     * Public brand name.
     */
    public function publicName(): Validity
    {
        return $this->baseValidity->make()->string(120);
    }

    /**
     * Optional voucher message.
     */
    public function message(): Validity
    {
        return $this->baseValidity->make()->string(240);
    }

    /**
     * Optional safe raster logo.
     */
    public function logo(): Validity
    {
        return $this->baseValidity->make()
            ->image(5120, ['image/jpeg', 'image/png', 'image/webp'])
            ->dimensionsRule(maxWidth: 6000, maxHeight: 6000);
    }

    /**
     * Explicit current-logo removal flag.
     */
    public function removeLogo(): Validity
    {
        return $this->baseValidity->make()->boolean();
    }
}
