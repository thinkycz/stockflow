<?php

declare(strict_types=1);

use App\Models\ShiftRequest;
use App\Models\Store;
use App\Models\Worker;
use Thinkycz\LaravelCore\Support\Typer;

\test('default shift request factory creates a consistent ownership graph', function (): void {
    $shiftRequest = ShiftRequest::factory()->createOne();
    $store = Typer::assertInstance(Store::query()->find($shiftRequest->getStoreId()), Store::class);
    $worker = Typer::assertInstance(Worker::query()->find($shiftRequest->getWorkerId()), Worker::class);

    \expect($store->getUserId())->toBe($shiftRequest->getUserId())
        ->and($worker->getUserId())->toBe($shiftRequest->getUserId())
        ->and($shiftRequest->getStartTimeShort())->toMatch('/^\\d{2}:\\d{2}$/');
});
