<?php

declare(strict_types=1);

use App\Models\Worker;
use Carbon\CarbonImmutable;

\test('admin restores their archived worker to active selectors', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'archived_at' => CarbonImmutable::now(),
    ]);

    $this->be($admin, 'users')
        ->post("/workers/{$worker->getKey()}/restore")
        ->assertRedirect('/workers?status=archived');

    \expect($worker->refresh()->isArchived())->toBeFalse();
});

\test('admin cannot restore another administrators worker', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$otherAdmin] = \createIsolatedUserWithWarehouse();
    $worker = Worker::factory()->create([
        'user_id' => $otherAdmin->getKey(),
        'archived_at' => CarbonImmutable::now(),
    ]);

    $this->be($admin, 'users')
        ->post("/workers/{$worker->getKey()}/restore")
        ->assertNotFound();

    \expect($worker->refresh()->isArchived())->toBeTrue();
});
