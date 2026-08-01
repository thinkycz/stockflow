<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Thinkycz\LaravelCore\Support\Typer;

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

        $worker = Worker::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'first_name' => 'E2E',
                'last_name' => 'Worker',
            ],
            ['hourly_rate' => 200],
        );
        $secondWorker = Worker::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'first_name' => 'Active',
                'last_name' => 'Employee',
            ],
            ['hourly_rate' => 200],
        );
        $scheduledWorker = Worker::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'first_name' => 'Scheduled',
                'last_name' => 'Worker',
            ],
            ['hourly_rate' => 200],
        );
        $outsideWindowWorker = Worker::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'first_name' => 'Outside Window',
                'last_name' => 'Worker',
            ],
            ['hourly_rate' => 200],
        );
        Worker::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'first_name' => 'Off Schedule',
                'last_name' => 'Worker',
            ],
            ['hourly_rate' => 200],
        );
        Shift::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => $scheduledWorker->getKey(),
                'date' => CarbonImmutable::now('Europe/Prague')->toDateString(),
                'start_time' => '00:00',
            ],
            ['end_time' => '23:59'],
        );
        $localNow = CarbonImmutable::now('Europe/Prague');
        Shift::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => $outsideWindowWorker->getKey(),
                'date' => $localNow->toDateString(),
                'start_time' => $localNow->hour < 12 ? '20:00' : '02:00',
            ],
            ['end_time' => $localNow->hour < 12 ? '21:00' : '03:00'],
        );
        $limited = User::query()->where('email', 'limited@test.com')->first();

        if (!$limited instanceof User) {
            $limited = Typer::assertInstance(
                UserFactory::new()->limited($store)->password()->createOne([
                    'email' => 'limited@test.com',
                    'locale' => 'en',
                ]),
                User::class,
            );
        }

        foreach ([$worker, $secondWorker] as $activeWorker) {
            if (AttendanceSession::query()->where('active_worker_id', $activeWorker->getKey())->exists()) {
                continue;
            }

            AttendanceSession::query()->create([
                'user_id' => $user->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => $activeWorker->getKey(),
                'created_by_user_id' => $limited->getKey(),
                'active_worker_id' => $activeWorker->getKey(),
                'hourly_rate' => $activeWorker->getHourlyRate(),
                'started_at' => CarbonImmutable::now('UTC')->subHour(),
            ]);
        }

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
