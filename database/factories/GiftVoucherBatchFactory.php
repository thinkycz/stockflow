<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GiftVoucherBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftVoucherBatch>
 */
class GiftVoucherBatchFactory extends Factory
{
    /**
     * Define a voucher batch.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = UserFactory::new()->admin()->createOne();

        return [
            'user_id' => $user->getKey(),
            'created_by_user_id' => $user->getKey(),
            'quantity' => 1,
            'amount' => '500.00',
            'expires_at' => null,
            'brand_name' => $this->faker->company(),
            'brand_message' => null,
            'brand_logo_path' => null,
            'brand_logo_mime' => null,
        ];
    }
}
