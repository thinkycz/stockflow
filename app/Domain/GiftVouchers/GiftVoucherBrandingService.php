<?php

declare(strict_types=1);

namespace App\Domain\GiftVouchers;

use App\Enums\FilesystemDiskEnum;
use App\Models\GiftVoucherSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Throwable;

class GiftVoucherBrandingService
{
    /**
     * Create or update the company's current voucher branding.
     */
    public function update(
        User $admin,
        string $publicName,
        string|null $message,
        UploadedFile|null $logo,
        bool $removeLogo,
    ): GiftVoucherSetting {
        if (!$admin->isAdmin()) {
            \abort(403);
        }

        $current = GiftVoucherSetting::query()->where('user_id', $admin->getKey())->first();
        $oldPath = $current?->getLogoPath();
        $stored = $this->storeLogo($admin, $logo);

        try {
            $setting = GiftVoucherSetting::query()->updateOrCreate(
                ['user_id' => $admin->getKey()],
                [
                    'public_name' => $publicName,
                    'message' => $message,
                    'logo_path' => $stored['path'] ?? ($removeLogo ? null : $oldPath),
                    'logo_mime' => $stored['mime'] ?? ($removeLogo ? null : $current?->getLogoMime()),
                ],
            );
        } catch (Throwable $throwable) {
            $this->delete($stored['path']);

            throw $throwable;
        }

        if (($stored['path'] !== null || $removeLogo) && $oldPath !== $stored['path']) {
            $this->delete($oldPath);
        }

        return $setting;
    }

    /**
     * Copy the current logo to an immutable batch snapshot.
     */
    public function snapshotLogo(GiftVoucherSetting $setting): string|null
    {
        $source = $setting->getLogoPath();

        if ($source === null) {
            return null;
        }

        $extension = \pathinfo($source, \PATHINFO_EXTENSION);
        $target = 'gift-vouchers/batches/' . $setting->getUserId() . '/' . \bin2hex(\random_bytes(20)) . '.' . $extension;
        $disk = Resolver::resolveFilesystemManager()->disk(FilesystemDiskEnum::Private->value);

        if (!$disk->copy($source, $target)) {
            throw new RuntimeException('Gift voucher logo snapshot could not be created.');
        }

        return $target;
    }

    /**
     * Return a private image as a browser-safe data URI.
     */
    public function dataUri(string|null $path, string|null $mime): string|null
    {
        if ($path === null || $mime === null) {
            return null;
        }

        $contents = Resolver::resolveFilesystemManager()->disk(FilesystemDiskEnum::Private->value)->get($path);

        if ($contents === null) {
            throw new RuntimeException('Gift voucher logo could not be read.');
        }

        return 'data:' . $mime . ';base64,' . \base64_encode($contents);
    }

    /**
     * Store an uploaded current logo.
     *
     * @return array{path: string|null, mime: string|null}
     */
    private function storeLogo(User $admin, UploadedFile|null $logo): array
    {
        if (!$logo instanceof UploadedFile) {
            return ['path' => null, 'mime' => null];
        }

        $extension = $logo->guessExtension();
        if (!\in_array($extension, ['jpeg', 'jpg', 'png', 'webp'], true)) {
            throw new RuntimeException('Validated voucher logo has an unsupported extension.');
        }

        $path = Resolver::resolveFilesystemManager()
            ->disk(FilesystemDiskEnum::Private->value)
            ->putFileAs(
                'gift-vouchers/settings/' . $admin->getKey(),
                $logo,
                \bin2hex(\random_bytes(20)) . '.' . $extension,
            );

        if ($path === false) {
            throw new RuntimeException('Gift voucher logo could not be stored.');
        }

        return ['path' => $path, 'mime' => $logo->getMimeType()];
    }

    /**
     * Delete a private branding image when present.
     */
    private function delete(string|null $path): void
    {
        if ($path !== null) {
            Resolver::resolveFilesystemManager()->disk(FilesystemDiskEnum::Private->value)->delete($path);
        }
    }
}
