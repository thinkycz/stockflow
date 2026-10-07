<?php

declare(strict_types=1);

use App\Domain\Noticeboard\NoticeboardCardService;
use App\Domain\Noticeboard\NoticeboardConfirmationService;
use App\Domain\Workforce\AttendanceService;
use App\Enums\AttendanceActionEnum;
use App\Enums\FilesystemDiskEnum;
use App\Models\AttendanceSession;
use App\Models\NoticeboardCard;
use App\Models\NoticeboardConfirmation;
use App\Models\NoticeboardConfirmationItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

\beforeEach(function (): void { Carbon::setTestNow('2026-10-07 06:00:00 UTC'); });
\afterEach(function (): void { Carbon::setTestNow(); });

/**
 * @return array{User, Store, Worker}
 */
function noticeboardArrivalContext(): array
{
    [$owner] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $owner->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $owner->getKey()]);

    return [$owner, $store, $worker];
}

\test('first arrival snapshots every eligible card without noticeboard pagination', function (): void {
    [$owner, $store, $worker] = \noticeboardArrivalContext();
    NoticeboardCard::factory()->count(28)->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07']);
    foreach ([null, '2026-10-06', '2026-10-08'] as $date) {
        NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => $date]);
    }
    NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07', 'expires_at' => Carbon::now()]);
    NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07'])->delete();
    NoticeboardCard::factory()->create(['display_on' => '2026-10-07']);

    $session = (new AttendanceService())->perform($owner, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    $confirmation = NoticeboardConfirmation::query()->sole();
    \expect($confirmation->getAttendanceSessionId())->toBe($session->getKey())
        ->and($confirmation->getWorkerId())->toBe($worker->getKey())
        ->and($confirmation->getWorkerName())->toBe($worker->getFullName())
        ->and($confirmation->getItems())->toHaveCount(28)
        ->and((new NoticeboardConfirmationService())->pendingForStore($owner, $store)['items'])->toHaveCount(28)
        ->and($session->getEndedAt())->toBeNull();
});

\test('later arrivals and card changes preserve the original daily list', function (): void {
    [$owner, $store, $worker] = \noticeboardArrivalContext();
    $card = NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07', 'body_html' => '<p>Original</p>']);
    $attendance = new AttendanceService();
    $attendance->perform($owner, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    $card->update(['body_html' => '<p>Changed later</p>', 'lock_version' => 2]);
    NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07']);
    $second = Worker::factory()->create(['user_id' => $owner->getKey()]);
    $attendance->perform($owner, $store, $second, AttendanceActionEnum::ARRIVAL, true);
    $attendance->perform($owner, $store, $worker, AttendanceActionEnum::DEPARTURE);
    $attendance->perform($owner, $store, $worker, AttendanceActionEnum::ARRIVAL, true);

    \expect(NoticeboardConfirmation::query()->count())->toBe(1)
        ->and(NoticeboardConfirmationItem::query()->sole()->getBodyHtml())->toBe('<p>Original</p>')
        ->and(NoticeboardConfirmationItem::query()->sole()->getCardVersion())->toBe(1)
        ->and(NoticeboardConfirmation::query()->sole()->getWorkerId())->toBe($worker->getKey());
});

\test('an empty first arrival cannot become a later reading obligation', function (): void {
    [$owner, $store, $worker] = \noticeboardArrivalContext();
    $attendance = new AttendanceService();
    $attendance->perform($owner, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07']);
    $attendance->perform($owner, $store, Worker::factory()->create(['user_id' => $owner->getKey()]), AttendanceActionEnum::ARRIVAL, true);

    \expect(NoticeboardConfirmation::query()->count())->toBe(1)
        ->and(NoticeboardConfirmationItem::query()->count())->toBe(0)
        ->and((new NoticeboardConfirmationService())->pendingForStore($owner, $store))->toBeNull();
});

\test('daily database uniqueness protects against competing confirmation creation', function (): void {
    [$owner, $store, $worker] = \noticeboardArrivalContext();
    (new AttendanceService())->perform($owner, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    $confirmation = NoticeboardConfirmation::query()->sole();
    \expect(fn() => $confirmation->replicate()->save())->toThrow(QueryException::class);
    \expect(NoticeboardConfirmation::query()->count())->toBe(1);
});

\test('arrival and reading snapshots roll back together when the transaction fails', function (): void {
    [$owner, $store, $worker] = \noticeboardArrivalContext();
    NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07']);
    \expect(function () use ($owner, $store, $worker): void {
        DB::transaction(static function () use ($owner, $store, $worker): void {
            (new AttendanceService())->perform($owner, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
            throw new RuntimeException('Injected transaction failure');
        });
    })->toThrow(RuntimeException::class);
    \expect(AttendanceSession::query()->count())->toBe(0)
        ->and(NoticeboardConfirmation::query()->count())->toBe(0)
        ->and(NoticeboardConfirmationItem::query()->count())->toBe(0);
});

\test('existing attendance prevents retroactive confirmation on deployment', function (): void {
    [$owner, $store, $worker] = \noticeboardArrivalContext();
    AttendanceSession::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'started_at' => Carbon::now()->subHour(), 'ended_at' => Carbon::now()->subMinutes(30), 'active_worker_id' => null]);
    NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07']);
    (new AttendanceService())->perform($owner, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    \expect(NoticeboardConfirmation::query()->count())->toBe(0);
});

\test('Prague business days and DST use local dates rather than UTC dates', function (string $instant, string $date): void {
    Carbon::setTestNow($instant);
    [$owner, $store, $worker] = \noticeboardArrivalContext();
    NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => $date]);
    (new AttendanceService())->perform($owner, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    \expect(NoticeboardConfirmation::query()->sole()->getDate())->toBe($date)
        ->and(NoticeboardConfirmationItem::query()->count())->toBe(1);
})->with([
    ['2026-10-07 22:30:00 UTC', '2026-10-08'],
    ['2026-10-25 00:30:00 UTC', '2026-10-25'],
    ['2026-10-25 23:30:00 UTC', '2026-10-26'],
]);

\test('confirmation requires every unique item and records the authenticated actor once', function (): void {
    [$owner, $store, $worker] = \noticeboardArrivalContext();
    NoticeboardCard::factory()->count(2)->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07']);
    $actor = UserFactory::new()->limited($store)->createOne();
    (new AttendanceService())->perform($actor, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    $confirmation = NoticeboardConfirmation::query()->sole();
    $ids = $confirmation->getItems()->modelKeys();
    $service = new NoticeboardConfirmationService();
    foreach ([[], [$ids[0]], [$ids[0], $ids[0]], [...$ids, 999999]] as $incomplete) {
        \expect(fn() => $service->confirm($actor, $store, $confirmation->getKey(), $incomplete))->toThrow(ValidationException::class);
    }
    $service->confirm($actor, $store, $confirmation->getKey(), \array_reverse($ids));
    $confirmedAt = $confirmation->refresh()->getConfirmedAt()?->toISOString();
    Carbon::setTestNow('2026-10-07 09:00:00 UTC');
    $service->confirm($owner, $store, $confirmation->getKey(), $ids);
    \expect($confirmation->refresh()->getConfirmedByUserId())->toBe($actor->getKey())
        ->and($confirmation->getConfirmedAt()?->toISOString())->toBe($confirmedAt)
        ->and($service->pendingForStore($actor, $store))->toBeNull();
});

\test('indicators follow the card date and preserve confirmed historical snapshots', function (): void {
    [$owner, $store, $worker] = \noticeboardArrivalContext();
    $card = NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07']);
    (new AttendanceService())->perform($owner, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    $confirmation = NoticeboardConfirmation::query()->sole();
    $service = new NoticeboardConfirmationService();
    \expect($service->confirmedCards($owner, $store, NoticeboardCard::query()->get()))->toBe([]);
    $service->confirm($owner, $store, $confirmation->getKey(), $confirmation->getItems()->modelKeys());
    \expect($service->confirmedCards($owner, $store, NoticeboardCard::query()->get())[$card->getKey()]['worker_name'])->toBe($worker->getFullName());
    foreach (['2026-10-08', null] as $newDate) {
        $card->update(['display_on' => $newDate]);
        \expect($service->confirmedCards($owner, $store, NoticeboardCard::query()->get()))->toBe([]);
    }
    \expect($confirmation->refresh()->getConfirmedAt())->not->toBeNull()
        ->and($confirmation->getItems())->toHaveCount(1);
});

\test('pending cards do not carry over into the next day', function (): void {
    [$owner, $store, $worker] = \noticeboardArrivalContext();
    NoticeboardCard::factory()->create(['user_id' => $owner->getKey(), 'store_id' => $store->getKey(), 'display_on' => '2026-10-07']);
    (new AttendanceService())->perform($owner, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    $confirmation = NoticeboardConfirmation::query()->sole();
    Carbon::setTestNow('2026-10-08 06:00:00 UTC');
    $service = new NoticeboardConfirmationService();
    \expect($service->pendingForStore($owner, $store))->toBeNull();
    \expect(fn() => $service->confirm($owner, $store, $confirmation->getKey(), $confirmation->getItems()->modelKeys()))->toThrow(ValidationException::class);
});

\test('domain rejects foreign actors inactive stores and warehouses', function (string $case): void {
    [$owner, $store] = \noticeboardArrivalContext();
    $actor = $owner;
    if ($case === 'foreign_admin') { $actor = UserFactory::new()->admin()->createOne(); }
    if ($case === 'other_store') { $actor = UserFactory::new()->limited(Store::factory()->create(['user_id' => $owner->getKey()]))->createOne(); }
    if ($case === 'inactive') { $store->update(['status' => 'inactive']); }
    if ($case === 'warehouse') { $store->update(['is_warehouse' => true]); }
    $service = new NoticeboardConfirmationService();
    \expect(fn() => $service->pendingForStore($actor, $store))->toThrow(HttpException::class);
    \expect(fn() => $service->confirm($actor, $store, 999, [1]))->toThrow(HttpException::class);
})->with(['foreign_admin', 'other_store', 'inactive', 'warehouse']);

\test('snapshotted image survives replacement removal and permanent deletion', function (): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    [$owner, $store, $worker] = \noticeboardArrivalContext();
    $service = new NoticeboardCardService();
    $card = $service->create($owner, $store, '<p>With original image</p>', 'event', 'pink', 'medium', null, UploadedFile::fake()->image('original.png'), '2026-10-07');
    $path = $card->getImagePath();
    (new AttendanceService())->perform($owner, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    $service->update($card, $owner, '<p>New image</p>', 'event', 'blue', 'medium', null, UploadedFile::fake()->image('replacement.png'), false, 1);
    $card->refresh();
    $replacementPath = $card->getImagePath();
    $service->update($card, $owner, '<p>No image</p>', 'event', 'blue', 'medium', null, null, true, 2);
    $service->trash($card->refresh(), $owner);
    $service->forceDelete($card->refresh(), $owner);
    Storage::disk(FilesystemDiskEnum::Private->value)->assertExists($path);
    Storage::disk(FilesystemDiskEnum::Private->value)->assertMissing($replacementPath);
    \expect(NoticeboardConfirmationItem::query()->sole()->getImagePath())->toBe($path)
        ->and(NoticeboardConfirmationItem::query()->sole()->getNoticeboardCardId())->toBeNull()
        ->and(NoticeboardConfirmationItem::query()->sole()->getBodyHtml())->toBe('<p>With original image</p>');
});
