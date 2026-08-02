<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GiftVoucherSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftVoucherSetting>
 */
class GiftVoucherSettingFactory extends Factory
{
    /**
     * Define default voucher branding.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new()->admin(),
            'public_name' => $this->faker->company(),
            'message' => $this->faker->sentence(),
            'logo_path' => null,
            'logo_mime' => null,
        ];
    }
}
