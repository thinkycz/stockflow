<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShiftPreset;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Seeder;

class E2ESeeder extends Seeder
{
    /**
     * Seed deterministic English credentials for browser tests.
     */
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        $user = User::query()->where('email', 'test@test.com')->first();

        if (!$user instanceof User) {
            return;
        }

        $store = Store::query()
            ->where('user_id', $user->getKey())
            ->where('is_warehouse', false)
            ->orderBy('name')
            ->first();

        $user->update([
            'locale' => 'en',
            'active_store_id' => $store?->getKey(),
        ]);

        if (!$store instanceof Store) {
            return;
        }

        Worker::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'first_name' => 'E2E',
                'last_name' => 'Worker',
            ],
            ['hourly_rate' => 200],
        );

        foreach ([
            ['name' => 'Morning', 'start_time' => '06:30', 'end_time' => '12:00'],
            ['name' => 'Evening', 'start_time' => '18:00', 'end_time' => '22:00'],
        ] as $preset) {
            ShiftPreset::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'store_id' => $store->getKey(),
                    'name' => $preset['name'],
                ],
                [
                    'start_time' => $preset['start_time'],
                    'end_time' => $preset['end_time'],
                ],
            );
        }
    }
}
