<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\NoticeboardCardColorEnum;
use App\Enums\NoticeboardCardLabelEnum;
use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class NoticeboardCardValidity
{
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
     * Card title.
     */
    public function title(): Validity
    {
        return $this->baseValidity->make()->string(120);
    }

    /**
     * Raw editor HTML.
     */
    public function bodyHtml(): Validity
    {
        return $this->baseValidity->make()->string(20_000);
    }

    /**
     * Card label.
     */
    public function label(): Validity
    {
        return $this->baseValidity->make()->string(32)->in(NoticeboardCardLabelEnum::values());
    }

    /**
     * Card color.
     */
    public function color(): Validity
    {
        return $this->baseValidity->make()->string(32)->in(NoticeboardCardColorEnum::values());
    }

    /**
     * Optional expiration date.
     */
    public function expiresOn(): Validity
    {
        return $this->baseValidity->date();
    }

    /**
     * Optional card image.
     */
    public function image(): Validity
    {
        return $this->baseValidity->make()
            ->image(5120, ['image/jpeg', 'image/png', 'image/webp'])
            ->dimensionsRule(maxWidth: 6000, maxHeight: 6000);
    }

    /**
     * Explicit image removal flag.
     */
    public function removeImage(): Validity
    {
        return $this->baseValidity->make()->boolean();
    }

    /**
     * Optimistic lock version.
     */
    public function lockVersion(): Validity
    {
        return $this->baseValidity->make()->integer(null, 1);
    }

    /**
     * Dashboard search.
     */
    public function search(): Validity
    {
        return $this->baseValidity->make()->string(120);
    }

    /**
     * Dashboard status.
     */
    public function status(): Validity
    {
        return $this->baseValidity->make()->string(16)->in(['active', 'expired', 'trash']);
    }
}
