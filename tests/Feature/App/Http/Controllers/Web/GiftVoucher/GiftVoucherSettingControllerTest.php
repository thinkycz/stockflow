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
    ])->assertRedirect('/gift-voucher-settings');

    $setting = GiftVoucherSetting::query()->where('user_id', $admin->getKey())->firstOrFail();
    \expect($setting->getPublicName())->toBe('Kavárna Orion')
        ->and($setting->getLogoPath())->not->toBeNull();
    Storage::disk('private')->assertExists($setting->getLogoPath());
    $logoPath = $setting->getLogoPath();
    $this->put('/gift-voucher-settings', [
        'public_name' => 'Kavárna Orion',
        'remove_logo' => true,
    ])->assertRedirect('/gift-voucher-settings');
    \expect($setting->refresh()->getLogoPath())->toBeNull();
    Storage::disk('private')->assertMissing($logoPath);
});
