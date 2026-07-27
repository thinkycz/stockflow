<?php

declare(strict_types=1);

use App\Models\AttendanceSession;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;

\test('admin can create a historical correction and limited user cannot', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $payload = [
        'worker_id' => $worker->getKey(), 'started_at' => '2026-07-20T08:00',
        'ended_at' => '2026-07-20T16:00', 'breaks' => [], 'reason' => 'Doplnění z papíru',
    ];

    $this->be($admin, 'users')->post('/attendance/corrections', $payload, $this->inertiaHeaders())->assertRedirect('/attendance/report');
    \expect(AttendanceSession::query()->count())->toBe(1);

    $limited = UserFactory::new()->limited($store)->createOne();
    $this->be($limited, 'users')->post('/attendance/corrections', $payload, $this->inertiaHeaders())->assertRedirect('/dashboard');
});

\test('admin can update and void an attendance session with audited reasons', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'started_at' => '2026-07-20 06:00:00', 'ended_at' => '2026-07-20 14:00:00',
    ]);

    $this->be($admin, 'users')->put('/attendance/sessions/' . $session->getKey(), [
        'worker_id' => $worker->getKey(), 'started_at' => '2026-07-20T08:15',
        'ended_at' => '2026-07-20T16:15', 'breaks' => [], 'reason' => 'Oprava zápisu',
    ], $this->inertiaHeaders())->assertRedirect('/attendance/report');
    $this->be($admin, 'users')->post('/attendance/sessions/' . $session->getKey() . '/void', [
        'reason' => 'Duplicitní záznam',
    ], $this->inertiaHeaders())->assertRedirect('/attendance/report');

    \expect($session->refresh()->getVoidedAt())->not->toBeNull()
        ->and($session->audits()->pluck('action')->all())->toBe(['correction_update', 'correction_void']);
});
