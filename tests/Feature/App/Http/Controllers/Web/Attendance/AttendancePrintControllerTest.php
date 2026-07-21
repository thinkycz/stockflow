<?php

declare(strict_types=1);

use App\Models\Store;

\test('attendance print page is available only to admin', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    Store::factory()->create(['user_id' => $admin->getKey()]);

    $this->be($admin, 'users')->get('/attendance/print?month=2026-07', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'attendance/Print')
        ->assertJsonPath('props.report.month', '2026-07');
});
