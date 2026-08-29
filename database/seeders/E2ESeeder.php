<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Ai\Agents\StockflowAssistant;
use App\Enums\AssistantTurnStatusEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\AssistantTurn;
use App\Models\AssistantTurnEvent;
use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\ShiftShareLink;
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

        $crossStore = Store::query()
            ->where('user_id', $user->getKey())
            ->whereKeyNot($store->getKey())
            ->where('is_warehouse', false)
            ->orderBy('name')
            ->first();

        if ($crossStore instanceof Store) {
            $conversation = $user->conversations()->updateOrCreate(
                ['id' => '019fef6f-a4ab-7813-a09c-518d7157e2e0'],
                ['title' => 'Pending cross-store transfer'],
            );
            $conversation->messages()->updateOrCreate(
                ['id' => '019fef6f-a4ab-7813-a09c-518d7157e2e1'],
                [
                    'participant_type' => $user->getMorphClass(),
                    'participant_id' => $user->getKey(),
                    'agent' => StockflowAssistant::class,
                    'role' => 'assistant',
                    'content' => '',
                    'attachments' => [],
                    'tool_calls' => [[
                        'id' => 'e2e-cross-store-transfer',
                        'name' => 'write_stock_movements',
                        'arguments' => [
                            'request' => [
                                'action' => 'create_stock_movement',
                                'mode' => 'transfer',
                                'store_id' => $crossStore->getKey(),
                                'source_store_id' => $store->getKey(),
                                'values' => [
                                    'note' => 'E2E transfer proposal',
                                    'items' => [],
                                ],
                            ],
                        ],
                    ]],
                    'tool_results' => [],
                    'usage' => [],
                    'meta' => [],
                    'approval_state' => ['pending' => [
                        'e2e-cross-store-transfer' => \json_encode([
                            'version' => 2,
                            'kind' => 'action_confirmation',
                            'summary_key' => 'assistant.action_summaries.create_stock_movement',
                            'summary_params' => [
                                'mode' => 'transfer',
                                'items_count' => 0,
                                'store' => $crossStore->getName(),
                            ],
                        ], \JSON_THROW_ON_ERROR),
                    ]],
                ],
            );
        }

        $workerConversation = $user->conversations()->updateOrCreate(
            ['id' => '019fef6f-a4ab-7813-a09c-518d7157e2e3'],
            ['title' => 'Pending worker creation'],
        );
        $workerConversation->messages()->updateOrCreate(
            ['id' => '019fef6f-a4ab-7813-a09c-518d7157e2e4'],
            [
                'participant_type' => $user->getMorphClass(),
                'participant_id' => $user->getKey(),
                'agent' => StockflowAssistant::class,
                'role' => 'assistant',
                'content' => '',
                'attachments' => [],
                'tool_calls' => [[
                    'id' => 'e2e-create-worker',
                    'name' => 'write_workers',
                    'arguments' => [
                        'request' => [
                            'action' => 'create_worker',
                            'values' => [
                                'first_name' => 'E2E',
                                'last_name' => 'Proposal',
                                'hourly_rate' => 130,
                            ],
                        ],
                    ],
                ]],
                'tool_results' => [],
                'usage' => [],
                'meta' => [],
                'approval_state' => ['pending' => [
                    'e2e-create-worker' => \json_encode([
                        'version' => 1,
                        'tool' => 'write_workers',
                        'operation' => 'create_worker',
                        'store' => null,
                        'sanitized_arguments' => [
                            'values' => [
                                'first_name' => 'E2E',
                                'last_name' => 'Proposal',
                                'hourly_rate' => 130,
                            ],
                        ],
                    ], \JSON_THROW_ON_ERROR),
                ]],
            ],
        );

        $resolvedFailureConversation = $user->conversations()->updateOrCreate(
            ['id' => '019fef6f-a4ab-7813-a09c-518d7157e2e5'],
            ['title' => 'Recovered assistant response'],
        );
        $resolvedFailureConversation->messages()->updateOrCreate(
            ['id' => '019fef6f-a4ab-7813-a09c-518d7157e2e6'],
            [
                'participant_type' => $user->getMorphClass(),
                'participant_id' => $user->getKey(),
                'agent' => StockflowAssistant::class,
                'role' => 'assistant',
                'content' => 'Recovered answer after retry.',
                'attachments' => [],
                'tool_calls' => [],
                'tool_results' => [],
                'usage' => [],
                'meta' => [],
            ],
        );
        $failedTurnId = '019fef6f-a4ab-7813-a09c-518d7157e2e7';
        $failedPayload = ['message' => 'Stale retry input'];
        AssistantTurn::query()->updateOrCreate(
            ['id' => $failedTurnId],
            [
                'actor_user_id' => $user->getKey(),
                'conversation_id' => $resolvedFailureConversation->getKey(),
                'parent_turn_id' => null,
                'kind' => 'message',
                'recovery_mode' => 'normal',
                'status' => AssistantTurnStatusEnum::FAILED->value,
                'input_hash' => \hash('sha256', \json_encode($failedPayload, \JSON_THROW_ON_ERROR)),
                'input_payload' => $failedPayload,
                'error_summary' => 'Temporary provider failure',
                'queued_at' => '2026-08-29 20:03:30',
                'started_at' => '2026-08-29 20:03:30',
                'completed_at' => '2026-08-29 20:03:30',
                'created_at' => '2026-08-29 20:03:30',
                'updated_at' => '2026-08-29 20:03:30',
            ],
        );
        $completedPayload = ['message' => 'Retry the interrupted response'];
        AssistantTurn::query()->updateOrCreate(
            ['id' => '019fef6f-a4ab-7813-a09c-518d7157e2e8'],
            [
                'actor_user_id' => $user->getKey(),
                'conversation_id' => $resolvedFailureConversation->getKey(),
                'parent_turn_id' => $failedTurnId,
                'kind' => 'message',
                'recovery_mode' => 'replay_without_action',
                'status' => AssistantTurnStatusEnum::COMPLETED->value,
                'input_hash' => \hash('sha256', \json_encode($completedPayload, \JSON_THROW_ON_ERROR)),
                'input_payload' => [],
                'error_summary' => null,
                'queued_at' => '2026-08-29 20:04:30',
                'started_at' => '2026-08-29 20:04:30',
                'completed_at' => '2026-08-29 20:04:31',
                'created_at' => '2026-08-29 20:04:30',
                'updated_at' => '2026-08-29 20:04:31',
            ],
        );

        $latestFailureConversation = $user->conversations()->updateOrCreate(
            ['id' => '019fef6f-a4ab-7813-a09c-518d7157e2e9'],
            ['title' => 'Latest assistant failure'],
        );
        $latestFailurePayload = ['message' => 'Latest failed input'];
        AssistantTurn::query()->updateOrCreate(
            ['id' => '019fef6f-a4ab-7813-a09c-518d7157e2ea'],
            [
                'actor_user_id' => $user->getKey(),
                'conversation_id' => $latestFailureConversation->getKey(),
                'parent_turn_id' => null,
                'kind' => 'message',
                'recovery_mode' => 'normal',
                'status' => AssistantTurnStatusEnum::FAILED->value,
                'input_hash' => \hash('sha256', \json_encode($latestFailurePayload, \JSON_THROW_ON_ERROR)),
                'input_payload' => $latestFailurePayload,
                'error_summary' => 'Temporary provider failure',
                'queued_at' => '2026-08-29 20:05:30',
                'started_at' => '2026-08-29 20:05:30',
                'completed_at' => '2026-08-29 20:05:30',
                'created_at' => '2026-08-29 20:05:30',
                'updated_at' => '2026-08-29 20:05:30',
            ],
        );
        AssistantTurnEvent::query()->updateOrCreate(
            [
                'turn_id' => '019fef6f-a4ab-7813-a09c-518d7157e2ea',
                'sequence' => 1,
            ],
            [
                'event_type' => 'text-delta',
                'payload' => [
                    'type' => 'text-delta',
                    'id' => 'e2e-interrupted-answer',
                    'delta' => 'This streamed answer remains visible after reload.',
                ],
            ],
        );

        ShiftShareLink::query()->updateOrCreate(
            ['token' => 'e2e-shift-calendar-token'],
            [
                'user_id' => $user->getKey(),
                'store_id' => $store->getKey(),
                'name' => 'E2E public calendar',
            ],
        );

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
        Worker::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'first_name' => 'Payroll Only',
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

        $activeAttendanceStartedAt = $localNow->subHour();

        if (!$activeAttendanceStartedAt->isSameDay($localNow)) {
            $activeAttendanceStartedAt = $localNow->startOfDay();
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
                'started_at' => $activeAttendanceStartedAt->utc(),
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
