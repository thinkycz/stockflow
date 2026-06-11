<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StoreStatusEnum;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Seed the application's stores.
     */
    public function run(): void
    {
        $user = User::query()->first();

        if (!$user instanceof User) {
            return;
        }

        Store::query()->updateOrCreate(
            ['user_id' => $user->getKey(), 'is_warehouse' => true],
            [
                'name' => 'Warehouse',
                'address' => null,
                'status' => StoreStatusEnum::ACTIVE->value,
                'notes' => 'Central warehouse.',
            ],
        );

        $stores = [
            [
                'name' => 'Praha centrála',
                'address' => 'Hlavní 123, 110 00 Praha 1',
                'status' => StoreStatusEnum::ACTIVE->value,
                'notes' => 'Hlavní prodejna.',
            ],
            [
                'name' => 'Brno pobočka',
                'address' => 'Masarykova 45, 602 00 Brno',
                'status' => StoreStatusEnum::ACTIVE->value,
                'notes' => 'Pobočka s denním odběrem.',
            ],
            [
                'name' => 'Ostrava depo',
                'address' => 'Slezská 78, 702 00 Ostrava',
                'status' => StoreStatusEnum::ACTIVE->value,
                'notes' => 'Depo pro severní Moravu.',
            ],
        ];

        foreach ($stores as $data) {
            Store::query()->updateOrCreate(
                ['user_id' => $user->getKey(), 'name' => $data['name']],
                [
                    'address' => $data['address'],
                    'status' => $data['status'],
                    'notes' => $data['notes'],
                    'is_warehouse' => false,
                ],
            );
        }
    }
}
