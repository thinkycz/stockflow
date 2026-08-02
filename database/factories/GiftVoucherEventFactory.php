<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GiftVoucherEventTypeEnum;
use App\Models\GiftVoucherEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftVoucherEvent>
 */
class GiftVoucherEventFactory extends Factory
{
    /**
     * Define an immutable voucher event.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gift_voucher_id' => GiftVoucherFactory::new(),
            'actor_user_id' => null,
            'store_id' => null,
            'type' => GiftVoucherEventTypeEnum::Issued->value,
            'reason' => null,
        ];
    }
}
