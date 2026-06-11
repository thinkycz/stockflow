<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Seed the application's items.
     */
    public function run(): void
    {
        $user = User::query()->first();

        if (!$user instanceof User) {
            return;
        }

        $items = [
            [
                'title' => 'Matcha Powder',
                'sku' => 'BUB-MAT-001',
                'unit' => 'g',
                'purchase_price' => 8.5,
                'description' => 'Ceremonial grade matcha, 100g pack.',
            ],
            [
                'title' => 'Tapioca Pearls',
                'sku' => 'BUB-TAP-002',
                'unit' => 'kg',
                'purchase_price' => 6.25,
                'description' => 'Black tapioca pearls, 1kg bag.',
            ],
            [
                'title' => 'Strawberry Syrup',
                'sku' => 'BUB-STR-003',
                'unit' => 'ml',
                'purchase_price' => 4.0,
                'description' => 'Strawberry fruit syrup, 750ml bottle.',
            ],
            [
                'title' => 'Plastic Cups 500ml',
                'sku' => 'BUB-CUP-004',
                'unit' => 'pcs',
                'purchase_price' => 0.12,
                'description' => 'Clear PET cups, 500ml, pack of 50.',
            ],
            [
                'title' => 'Bubble Tea Straws',
                'sku' => 'BUB-STRW-005',
                'unit' => 'pcs',
                'purchase_price' => 0.05,
                'description' => 'Wide bubble tea straws, 12mm, pack of 100.',
            ],
            [
                'title' => 'Brown Sugar Syrup',
                'sku' => 'BUB-BRS-006',
                'unit' => 'ml',
                'purchase_price' => 5.5,
                'description' => 'Brown sugar syrup, 750ml bottle.',
            ],
            [
                'title' => 'Milk Tea Powder',
                'sku' => 'BUB-MTP-007',
                'unit' => 'g',
                'purchase_price' => 7.2,
                'description' => 'Premium milk tea powder, 1kg pack.',
            ],
        ];

        foreach ($items as $data) {
            Item::query()->updateOrCreate(
                ['user_id' => $user->getKey(), 'sku' => $data['sku']],
                $data,
            );
        }
    }
}
