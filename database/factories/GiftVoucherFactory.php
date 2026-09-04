<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\GiftVouchers\GiftVoucherService;
use App\Enums\GiftVoucherStatusEnum;
use App\Models\GiftVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftVoucher>
 */
class GiftVoucherFactory extends Factory
{
    /**
     * Define a voucher.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $batch = GiftVoucherBatchFactory::new()->createOne();
        $code = GiftVoucherService::generateCode();

        return [
            'gift_voucher_batch_id' => $batch->getKey(),
            'user_id' => $batch->getUserId(),
            'code' => $code,
            'code_hash' => GiftVoucherService::hashCode($code),
            'status' => GiftVoucherStatusEnum::Active->value,
            'redeemed_at' => null,
            'redeemed_store_id' => null,
            'redeemed_by_user_id' => null,
        ];
    }
}
