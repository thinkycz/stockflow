<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FilesystemDiskEnum;
use App\Models\NoticeboardCard;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Throwable;

class NoticeboardCardService
{
    /**
     * Create a card and its optional private image.
     */
    public function create(
        User $actor,
        Store $store,
        string $title,
        string $bodyHtml,
        string $label,
        string $color,
        string $size,
        string|null $expiresOn,
        UploadedFile|null $image,
    ): NoticeboardCard {
        $content = (new NoticeboardContentSanitizer())->sanitize($bodyHtml);
        $imageData = $this->storeImage($store, $image);

        try {
            return DB::transaction(fn(): NoticeboardCard => NoticeboardCard::query()->create([
                'user_id' => $actor->resolveScopeUser()->getKey(),
                'store_id' => $store->getKey(),
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
                'title' => $title,
                'body_html' => $content['html'],
                'body_text' => $content['text'],
                'label' => $label,
                'color' => $color,
                'size' => $size,
                'image_path' => $imageData['path'],
                'image_mime' => $imageData['mime'],
                'expires_at' => $this->expiration($expiresOn),
                'lock_version' => 1,
            ]));
        } catch (Throwable $throwable) {
            $this->deleteImage($imageData['path']);

            throw $throwable;
        }
    }

    /**
     * Update a card while rejecting stale editor state.
     */
    public function update(
        NoticeboardCard $card,
        User $actor,
        string $title,
        string $bodyHtml,
        string $label,
        string $color,
        string $size,
        string|null $expiresOn,
        UploadedFile|null $image,
        bool $removeImage,
        int $lockVersion,
    ): NoticeboardCard {
        $content = (new NoticeboardContentSanitizer())->sanitize($bodyHtml);
        $imageData = $this->storeImage($card->getStoreId(), $image);
        $oldImagePath = $card->getImagePath();

        try {
            $updated = DB::transaction(function () use (
                $card,
                $actor,
                $title,
                $content,
                $label,
                $color,
                $size,
                $expiresOn,
                $imageData,
                $removeImage,
                $lockVersion,
            ): NoticeboardCard {
                $locked = NoticeboardCard::query()
                    ->whereKey($card->getKey())
                    ->where('store_id', $card->getStoreId())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockVersion !== $locked->getLockVersion()) {
                    Thrower::default()
                        ->message('lock_version', \__('This card changed while you were editing it. Refresh and try again.'))
                        ->throw();
                }

                $locked->setAttribute('title', $title);
                $locked->setAttribute('body_html', $content['html']);
                $locked->setAttribute('body_text', $content['text']);
                $locked->setAttribute('label', $label);
                $locked->setAttribute('color', $color);
                $locked->setAttribute('size', $size);
                $locked->setAttribute('expires_at', $this->expiration($expiresOn));
                $locked->setAttribute('updated_by_user_id', $actor->getKey());
                $locked->setAttribute('lock_version', $lockVersion + 1);

                if ($imageData['path'] !== null) {
                    $locked->setAttribute('image_path', $imageData['path']);
                    $locked->setAttribute('image_mime', $imageData['mime']);
                } elseif ($removeImage) {
                    $locked->setAttribute('image_path', null);
                    $locked->setAttribute('image_mime', null);
                }

                $locked->save();

                return $locked;
            });
        } catch (Throwable $throwable) {
            $this->deleteImage($imageData['path']);

            throw $throwable;
        }

        if (($imageData['path'] !== null || $removeImage) && $oldImagePath !== $imageData['path']) {
            $this->deleteImage($oldImagePath);
        }

        return $updated;
    }

    /**
     * Move a card to the recycle bin.
     */
    public function trash(NoticeboardCard $card): void
    {
        $card->delete();
    }

    /**
     * Restore a soft-deleted card.
     */
    public function restore(NoticeboardCard $card): void
    {
        $card->restore();
    }

    /**
     * Permanently remove a card after deleting its image.
     */
    public function forceDelete(NoticeboardCard $card): bool
    {
        $path = $card->getImagePath();

        if ($path !== null && !$this->deleteImage($path)) {
            return false;
        }

        return (bool) $card->forceDelete();
    }

    /**
     * Delete a private image when present.
     */
    private function deleteImage(string|null $path): bool
    {
        if ($path === null) {
            return true;
        }

        $disk = Resolver::resolveFilesystemManager()->disk(FilesystemDiskEnum::Private->value);

        return !$disk->exists($path) || $disk->delete($path);
    }

    /**
     * Store an uploaded image on the private disk.
     *
     * @return array{path: string|null, mime: string|null}
     */
    private function storeImage(int|Store $store, UploadedFile|null $image): array
    {
        if (!$image instanceof UploadedFile) {
            return ['path' => null, 'mime' => null];
        }

        $storeId = $store instanceof Store ? $store->getKey() : $store;
        $extension = $image->guessExtension();

        if (!\in_array($extension, ['jpeg', 'jpg', 'png', 'webp'], true)) {
            throw new RuntimeException('Validated image has an unsupported extension.');
        }

        $path = Resolver::resolveFilesystemManager()
            ->disk(FilesystemDiskEnum::Private->value)
            ->putFileAs(
                'noticeboard/' . $storeId,
                $image,
                \bin2hex(\random_bytes(20)) . '.' . $extension,
            );

        if ($path === false) {
            throw new RuntimeException('Noticeboard image could not be stored.');
        }

        return ['path' => $path, 'mime' => $image->getMimeType()];
    }

    /**
     * Convert a local expiration date to the end of day in UTC.
     */
    private function expiration(string|null $expiresOn): Carbon|null
    {
        if ($expiresOn === null || $expiresOn === '') {
            return null;
        }

        return Carbon::parse($expiresOn, 'Europe/Prague')->endOfDay()->utc();
    }
}
