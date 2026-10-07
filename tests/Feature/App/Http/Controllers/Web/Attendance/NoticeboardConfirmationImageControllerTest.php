<?php

declare(strict_types=1);

use App\Domain\Noticeboard\NoticeboardCardService;
use App\Domain\Workforce\AttendanceService;
use App\Enums\AttendanceActionEnum;
use App\Enums\FilesystemDiskEnum;
use App\Models\NoticeboardConfirmationItem;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

\test('private snapshot images remain readable after deletion only in the owning store', function (): void {
    Carbon::setTestNow('2026-10-07 06:00:00 UTC');
    try {
        Storage::fake(FilesystemDiskEnum::Private->value);
        [$owner] = \createIsolatedUserWithWarehouse();
        $store = Store::factory()->create(['user_id' => $owner->getKey(), 'is_warehouse' => false]);
        $actor = UserFactory::new()->limited($store)->createOne();
        $service = new NoticeboardCardService();
        $card = $service->create($owner, $store, '<p>Original image</p>', 'event', 'pink', 'medium', null, UploadedFile::fake()->image('notice.png'), '2026-10-07');
        (new AttendanceService())->perform($actor, $store, Worker::factory()->create(['user_id' => $owner->getKey()]), AttendanceActionEnum::ARRIVAL, true);
        $item = NoticeboardConfirmationItem::query()->sole();
        $contents = Storage::disk(FilesystemDiskEnum::Private->value)->get($item->getImagePath());
        $service->trash($card, $owner);
        $service->forceDelete($card, $owner);
        $url = '/attendance/noticeboard-confirmation-items/' . $item->getKey() . '/image';
        $this->be($actor, 'users')->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertStreamedContent($contents);
        $foreign = UserFactory::new()->limited(Store::factory()->create(['user_id' => $owner->getKey(), 'is_warehouse' => false]))->createOne();
        $this->be($foreign, 'users')->get($url)->assertNotFound();
    } finally {
        Carbon::setTestNow();
    }
});
