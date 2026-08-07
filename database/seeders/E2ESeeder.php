<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StockMovementTypeEnum;
use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\StockMovement;
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

        $store->update(['shift_share_token' => 'e2e-shift-calendar-token']);

        $warehouse = Store::query()
            ->where('user_id', $user->getKey())
            ->where('is_warehouse', true)
            ->first();

        StockMovement::query()->updateOrCreate(
            ['number' => 'IN-2030-E2E'],
            [
                'user_id' => $user->getKey(),
                'store_id' => $store->getKey(),
                'source_store_id' => null,
                'type' => StockMovementTypeEnum::INCOMING->value,
                'occurred_at' => '2030-01-10 10:00:00',
                'total_value' => 100,
            ],
        );
        if ($warehouse instanceof Store) {
            StockMovement::query()->updateOrCreate(
                ['number' => 'TR-2030-E2E'],
                [
                    'user_id' => $user->getKey(),
                    'store_id' => $store->getKey(),
                    'source_store_id' => $warehouse->getKey(),
                    'type' => StockMovementTypeEnum::TRANSFER->value,
                    'occurred_at' => '2030-01-11 10:00:00',
                    'total_value' => 200,
                ],
            );
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
        $deviationShift = Shift::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => $worker->getKey(),
                'date' => '2031-02-15',
            ],
            [
                'start_time' => '08:00',
                'end_time' => '16:00',
                'hourly_rate' => $worker->getHourlyRate(),
            ],
        );
        AttendanceSession::query()->updateOrCreate(
            ['shift_id' => $deviationShift->getKey()],
            [
                'user_id' => $user->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => $worker->getKey(),
                'created_by_user_id' => $user->getKey(),
                'active_worker_id' => null,
                'scheduled_date' => '2031-02-15',
                'scheduled_start_time' => '08:00',
                'scheduled_end_time' => '16:00',
                'hourly_rate' => $worker->getHourlyRate(),
                'started_at' => '2031-02-15 07:20:00',
                'ended_at' => '2031-02-15 15:30:00',
                'voided_at' => null,
                'voided_by_user_id' => null,
            ],
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
