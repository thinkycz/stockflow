<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use Thinkycz\LaravelCore\Support\Typer;

\test('default shift factory creates a consistent ownership graph', function (): void {
    $shift = Shift::factory()->createOne();
    $store = Typer::assertInstance(Store::query()->find($shift->getStoreId()), Store::class);
    $worker = Typer::assertInstance(Worker::query()->find($shift->getWorkerId()), Worker::class);

    \expect($store->getUserId())->toBe($shift->getUserId())
        ->and($worker->getUserId())->toBe($shift->getUserId());
});

\test('duration minutes handles zero-padded morning hours', function (): void {
    $shift = Shift::factory()->create([
        'start_time' => '09:00',
        'end_time' => '16:00',
    ]);

    \expect($shift->getDurationMinutes())->toBe(420);
});
