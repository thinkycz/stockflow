<?php

declare(strict_types=1);

use App\Domain\Checklists\ChecklistService;
use App\Models\ChecklistEvent;
use App\Models\ChecklistItem;
use App\Models\Store;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('limited store account completes and reopens todays item for selected worker', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 09:00:00', 'Europe/Prague'));
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $day = (new ChecklistService())->ensureDay($store, CarbonImmutable::now('Europe/Prague'));
    $item = Typer::assertInstance($day->items()->firstOrFail(), ChecklistItem::class);

    $this->be($limited, 'users')->put(\route('checklist-items.update', $item->getKey()), [
        'completed' => true,
        'worker_id' => $worker->getKey(),
        'lock_version' => 1,
    ])->assertRedirect();

    \expect($item->fresh()?->getCompletedByWorkerId())->toBe($worker->getKey())
        ->and(ChecklistEvent::query()->count())->toBe(1);

    $this->be($limited, 'users')->put(\route('checklist-items.update', $item->getKey()), [
        'completed' => false,
        'worker_id' => $worker->getKey(),
        'lock_version' => 2,
    ])->assertRedirect();

    \expect($item->fresh()?->getCompletedAt())->toBeNull()
        ->and(ChecklistEvent::query()->count())->toBe(2);
});

\test('checklist item update rejects stale historical foreign-store and foreign-worker writes', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 09:00:00', 'Europe/Prague'));
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $foreignAdmin = UserFactory::new()->admin()->createOne();
    $foreignWorker = Worker::factory()->create(['user_id' => $foreignAdmin->getKey()]);
    $service = new ChecklistService();
    $day = $service->ensureDay($store, CarbonImmutable::now('Europe/Prague'));
    $otherDay = $service->ensureDay($otherStore, CarbonImmutable::now('Europe/Prague'));
    $item = Typer::assertInstance($day->items()->firstOrFail(), ChecklistItem::class);
    $otherItem = Typer::assertInstance($otherDay->items()->firstOrFail(), ChecklistItem::class);

    $this->be($limited, 'users')->put(\route('checklist-items.update', $otherItem->getKey()), [
        'completed' => true, 'worker_id' => $worker->getKey(), 'lock_version' => 1,
    ])->assertNotFound();

    $this->be($limited, 'users')->put(\route('checklist-items.update', $item->getKey()), [
        'completed' => true, 'worker_id' => $foreignWorker->getKey(), 'lock_version' => 1,
    ], $this->inertiaHeaders())->assertRedirect()->assertSessionHasErrors('worker_id');

    $this->be($limited, 'users')->put(\route('checklist-items.update', $item->getKey()), [
        'completed' => true, 'worker_id' => $worker->getKey(), 'lock_version' => 1,
    ])->assertRedirect();
    $this->be($limited, 'users')->put(\route('checklist-items.update', $item->getKey()), [
        'completed' => true, 'worker_id' => $worker->getKey(), 'lock_version' => 1,
    ], [...$this->inertiaHeaders(), 'X-StockFlow-Action' => 'true'])->assertRedirect()->assertSessionHasErrors('lock_version');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-04 00:01:00', 'Europe/Prague'));
    $this->be($limited, 'users')->put(\route('checklist-items.update', $item->getKey()), [
        'completed' => false, 'worker_id' => $worker->getKey(), 'lock_version' => 2,
    ], [...$this->inertiaHeaders(), 'X-StockFlow-Action' => 'true'])->assertRedirect()->assertSessionHasErrors('checklist');
});
