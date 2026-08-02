<?php

declare(strict_types=1);

use App\Models\GiftVoucherSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

\test('administrator stores gift voucher branding and logo privately', function (): void {
    Storage::fake('private');
    [$admin] = \createIsolatedUserWithWarehouse();

    $this->be($admin, 'users')->put('/gift-voucher-settings', [
        'public_name' => 'Kavárna Orion',
        'message' => 'Dárek pro radost.',
        'logo' => UploadedFile::fake()->image('logo.png', 320, 120),
    ])->assertRedirect();

    $setting = GiftVoucherSetting::query()->where('user_id', $admin->getKey())->firstOrFail();
    \expect($setting->getPublicName())->toBe('Kavárna Orion')
        ->and($setting->getLogoPath())->not->toBeNull();
    Storage::disk('private')->assertExists($setting->getLogoPath());
});
