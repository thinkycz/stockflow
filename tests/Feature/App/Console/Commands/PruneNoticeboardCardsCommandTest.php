<?php

declare(strict_types=1);

use App\Enums\FilesystemDiskEnum;
use App\Models\NoticeboardCard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

\test('prune noticeboard cards permanently removes only trash older than thirty days', function (): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $old = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
        'image_path' => 'noticeboard/old.png',
        'image_mime' => 'image/png',
    ]);
    $recent = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
    ]);
    Storage::disk(FilesystemDiskEnum::Private->value)->put('noticeboard/old.png', 'image');
    $old->delete();
    $recent->delete();
    NoticeboardCard::query()->withTrashed()->whereKey($old->getKey())->update([
        'deleted_at' => Carbon::now()->subDays(31),
    ]);

    $this->artisan('stockflow:prune-noticeboard-cards')->assertSuccessful();

    \expect(NoticeboardCard::query()->withTrashed()->find($old->getKey()))->toBeNull()
        ->and(NoticeboardCard::query()->withTrashed()->find($recent->getKey()))->not->toBeNull();
    Storage::disk(FilesystemDiskEnum::Private->value)->assertMissing('noticeboard/old.png');
});
