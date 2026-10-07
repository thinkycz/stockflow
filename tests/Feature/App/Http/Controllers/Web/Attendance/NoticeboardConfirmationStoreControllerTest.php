<?php

declare(strict_types=1);

use App\Domain\Workforce\AttendanceService;
use App\Enums\AttendanceActionEnum;
use App\Models\NoticeboardCard;
use App\Models\NoticeboardConfirmation;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

\beforeEach(function (): void { Carbon::setTestNow('2026-10-07 06:00:00 UTC'); });
\afterEach(function (): void { Carbon::setTestNow(); });

\test('assigned account sees persistent reading dialog and confirms the complete list', function (): void {
    [$owner] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $owner->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $owner->getKey()]);
    $actor = UserFactory::new()->limited($store)->createOne();
    NoticeboardCard::factory()->count(2)->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07']);
    $this->be($actor, 'users')->post('/attendance/actions', ['worker_id' => $worker->getKey(), 'action' => 'arrival', 'confirm_without_shift' => true], $this->inertiaHeaders())->assertRedirect('/attendance');
    $confirmation = NoticeboardConfirmation::query()->sole();
    $ids = $confirmation->getItems()->modelKeys();
    foreach ([1, 2] as $_) {
        $this->get('/attendance')->assertInertia(fn(AssertableInertia $page) => $page
            ->where('noticeboard_confirmation.id', $confirmation->getKey())
            ->where('noticeboard_confirmation.worker.name', $worker->getFullName())
            ->has('noticeboard_confirmation.items', 2));
    }
    $url = '/attendance/noticeboard-confirmations/' . $confirmation->getKey();
    $this->post($url, ['item_ids' => [$ids[0]]], $this->inertiaHeaders())->assertRedirect()->assertSessionHasErrors('item_ids');
    \expect($confirmation->refresh()->getConfirmedAt())->toBeNull();
    $this->post($url, ['item_ids' => $ids], $this->inertiaHeaders())->assertRedirect('/attendance');
    \expect($confirmation->refresh()->getConfirmedByUserId())->toBe($actor->getKey())
        ->and($confirmation->getWorkerId())->toBe($worker->getKey());
    $this->get('/attendance')->assertInertia(fn(AssertableInertia $page) => $page->where('noticeboard_confirmation', null));
    $this->get('/dashboard')->assertInertia(fn(AssertableInertia $page) => $page
        ->has('noticeboard.cards', 2)
        ->where('noticeboard.cards.0.confirmation.worker_name', $worker->getFullName())
        ->where('noticeboard.cards.1.confirmation.worker_name', $worker->getFullName()));
    $this->post($url, ['item_ids' => $ids], $this->inertiaHeaders())->assertRedirect('/attendance');
});

\test('other store and other company cannot confirm another stores cards', function (): void {
    [$owner] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $owner->getKey(), 'is_warehouse' => false]);
    NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07']);
    (new AttendanceService())->perform($owner, $store, Worker::factory()->create(['user_id' => $owner->getKey()]), AttendanceActionEnum::ARRIVAL, true);
    $confirmation = NoticeboardConfirmation::query()->sole();
    $otherStore = Store::factory()->create(['user_id' => $owner->getKey(), 'is_warehouse' => false]);
    $otherCompany = UserFactory::new()->admin()->createOne();
    Store::factory()->create(['user_id' => $otherCompany->getKey(), 'is_warehouse' => false]);
    foreach ([UserFactory::new()->limited($otherStore)->createOne(), $otherCompany] as $actor) {
        $this->be($actor, 'users')->post('/attendance/noticeboard-confirmations/' . $confirmation->getKey(), ['item_ids' => $confirmation->getItems()->modelKeys()])->assertNotFound();
    }
    \expect($confirmation->refresh()->getConfirmedAt())->toBeNull();
});
