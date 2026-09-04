<?php

declare(strict_types=1);

namespace App\Domain\Noticeboard;

use App\Enums\FilesystemDiskEnum;
use App\Models\NoticeboardCard;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        string $bodyHtml,
        string $label,
        string $color,
        string $size,
        string|null $expiresOn,
        UploadedFile|null $image,
    ): NoticeboardCard {
        $this->authorize($actor, $store->getUserId(), $store->getKey());
        $content = (new NoticeboardContentSanitizer())->sanitize($bodyHtml);
        $imageData = $this->storeImage($store, $image);

        try {
            return DB::transaction(function () use ($actor, $store, $content, $label, $color, $size, $expiresOn, $imageData): NoticeboardCard {
                $store = $this->lockActiveStore($actor->resolveScopeUser()->getKey(), $store->getKey());

                return NoticeboardCard::query()->create([
                    'user_id' => $actor->resolveScopeUser()->getKey(),
                    'store_id' => $store->getKey(),
                    'created_by_user_id' => $actor->getKey(),
                    'updated_by_user_id' => $actor->getKey(),
                    'title' => $this->title($content['text']),
                    'body_html' => $content['html'],
                    'body_text' => $content['text'],
                    'label' => $label,
                    'color' => $color,
                    'size' => $size,
                    'image_path' => $imageData['path'],
                    'image_mime' => $imageData['mime'],
                    'expires_at' => $this->expiration($expiresOn),
                    'lock_version' => 1,
                ]);
            });
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
        string $bodyHtml,
        string $label,
        string $color,
        string $size,
        string|null $expiresOn,
        UploadedFile|null $image,
        bool $removeImage,
        int $lockVersion,
    ): NoticeboardCard {
        $this->authorize($actor, $card->getUserId(), $card->getStoreId());
        $content = (new NoticeboardContentSanitizer())->sanitize($bodyHtml);
        $imageData = $this->storeImage($card->getStoreId(), $image);
        $oldImagePath = $card->getImagePath();

        try {
            $updated = DB::transaction(function () use (
                $card,
                $actor,
                $content,
                $label,
                $color,
                $size,
                $expiresOn,
                $imageData,
                $removeImage,
                $lockVersion,
            ): NoticeboardCard {
                $this->lockActiveStore($actor->resolveScopeUser()->getKey(), $card->getStoreId());
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

                $locked->setAttribute('title', $this->title($content['text']));
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
            $this->deleteImageAfterCommit($oldImagePath);
        }

        return $updated;
    }

    /**
     * Move a card to the recycle bin.
     */
    public function trash(NoticeboardCard $card, User $actor): void
    {
        $this->authorize($actor, $card->getUserId(), $card->getStoreId(), false);
        DB::transaction(function () use ($card): void {
            $this->lockActiveStore($card->getUserId(), $card->getStoreId());
            NoticeboardCard::query()
                ->where('store_id', $card->getStoreId())
                ->whereKey($card->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();
        });
    }

    /**
     * Restore a soft-deleted card.
     */
    public function restore(NoticeboardCard $card, User $actor): void
    {
        $this->authorize($actor, $card->getUserId(), $card->getStoreId(), true);
        DB::transaction(function () use ($card): void {
            $this->lockActiveStore($card->getUserId(), $card->getStoreId());
            NoticeboardCard::query()
                ->withTrashed()
                ->where('store_id', $card->getStoreId())
                ->whereKey($card->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->restore();
        });
    }

    /**
     * Permanently remove a card after deleting its image.
     */
    public function forceDelete(NoticeboardCard $card, User $actor): bool
    {
        $this->authorize($actor, $card->getUserId(), $card->getStoreId(), true);

        return DB::transaction(function () use ($card): bool {
            $this->lockActiveStore($card->getUserId(), $card->getStoreId());
            $locked = NoticeboardCard::query()
                ->withTrashed()
                ->where('store_id', $card->getStoreId())
                ->whereKey($card->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->deleteImageAfterCommit($locked->getImagePath());

            return (bool) $locked->forceDelete();
        });
    }

    /**
     * Enforce company, assigned-store and administrator-only operations.
     */
    private function authorize(User $actor, int $ownerId, int $storeId, bool $adminOnly = false): void
    {
        if ($ownerId !== $actor->resolveScopeUser()->getKey() ||
            (!$actor->isAdmin() && ($adminOnly || $storeId !== $actor->getAssignedStoreId()))) {
            \abort(403);
        }
    }

    /**
     * Delete obsolete files only after the outer mutation and audit commit.
     */
    private function deleteImageAfterCommit(string|null $path): void
    {
        DB::afterCommit(function () use ($path): void {
            if (!$this->deleteImage($path)) {
                Thrower::default()->message('card', \__('The card image could not be deleted.'))->throw();
            }
        });
    }

    /**
     * Lock and recheck the owning store before any prospective card mutation.
     */
    private function lockActiveStore(int $userId, int $storeId): Store
    {
        $store = Store::query()
            ->where('user_id', $userId)
            ->whereKey($storeId)
            ->lockForUpdate()
            ->firstOrFail();

        if (!$store->isActive()) {
            \abort(404);
        }

        return $store;
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

    /**
     * Derive the internal searchable summary from card content.
     */
    private function title(string $bodyText): string
    {
        return Str::limit($bodyText, 120, '');
    }
}
