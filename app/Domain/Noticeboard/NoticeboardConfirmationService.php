<?php

declare(strict_types=1);

namespace App\Domain\Noticeboard;

use App\Models\AttendanceSession;
use App\Models\NoticeboardCard;
use App\Models\NoticeboardConfirmation;
use App\Models\NoticeboardConfirmationItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;

class NoticeboardConfirmationService
{
    /**
     * Capture the daily reading list in the arrival transaction.
     */
    public function initializeForArrival(User $actor, Store $store, Worker $worker, AttendanceSession $session): void
    {
        DB::transaction(function () use ($actor, $store, $worker, $session): void {
            $store = $this->lockStore($actor, $store);
            if ($session->getUserId() !== $store->getUserId() || $session->getStoreId() !== $store->getKey() ||
                $session->getWorkerId() !== $worker->getKey() || $worker->getUserId() !== $store->getUserId()) {
                \abort(403);
            }

            $day = CarbonImmutable::instance($session->getStartedAt())->setTimezone('Europe/Prague')->startOfDay();
            if (NoticeboardConfirmation::query()->where('store_id', $store->getKey())->where('date', $day->toDateString())->exists() ||
                AttendanceSession::query()->where('store_id', $store->getKey())
                    ->where('id', '<>', $session->getKey())
                    ->where('started_at', '>=', $day->utc())
                    ->where('started_at', '<', $day->addDay()->utc())->exists()) {
                return;
            }

            $confirmation = NoticeboardConfirmation::query()->create([
                'user_id' => $store->getUserId(), 'store_id' => $store->getKey(),
                'attendance_session_id' => $session->getKey(), 'worker_id' => $worker->getKey(),
                'worker_name' => $worker->getFullName(), 'date' => $day->toDateString(),
            ]);
            $query = NoticeboardCard::query();
            NoticeboardCard::scopeForUser($query, $actor->resolveScopeUser());
            NoticeboardCard::scopeForStore($query, $store->getKey());
            $cards = $query->where('display_on', $day->toDateString())
                ->where(static fn(Builder $dates) => $dates->whereNull('expires_at')->orWhere('expires_at', '>', $session->getStartedAt()))
                ->orderByDesc('created_at')->orderByDesc('id')->get();
            $rows = [];
            foreach ($cards as $position => $card) {
                $rows[] = [
                    'confirmation_id' => $confirmation->getKey(), 'noticeboard_card_id' => $card->getKey(),
                    'card_version' => $card->getLockVersion(), 'body_html' => $card->getBodyHtml(),
                    'label' => $card->getLabel()->value, 'color' => $card->getColor()->value,
                    'size' => $card->getSize()->value, 'image_path' => $card->getImagePath(),
                    'image_mime' => $card->getImageMime(), 'position' => $position,
                    'created_at' => $session->getStartedAt(), 'updated_at' => $session->getStartedAt(),
                ];
            }
            if ($rows !== []) {
                DB::table('noticeboard_confirmation_items')->insert($rows);
            }
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingForStore(User $actor, Store $store): array|null
    {
        $this->authorize($actor, $store);
        $confirmation = $this->query($actor, $store)->where('date', CarbonImmutable::now('Europe/Prague')->toDateString())
            ->whereNull('confirmed_at')->with('items')->first();
        if (!$confirmation instanceof NoticeboardConfirmation || $confirmation->getItems()->isEmpty()) {
            return null;
        }

        return [
            'id' => $confirmation->getKey(), 'date' => $confirmation->getDate(),
            'worker' => ['id' => $confirmation->getWorkerId(), 'name' => $confirmation->getWorkerName()],
            'items' => $confirmation->getItems()->map(static fn(NoticeboardConfirmationItem $item): array => [
                'id' => $item->getKey(), 'body_html' => $item->getBodyHtml(),
                'label' => $item->getLabel()->value, 'color' => $item->getColor()->value,
                'size' => $item->getSize()->value,
                'image_url' => $item->getImagePath() === null ? null : Resolver::resolveUrlGenerator()->route('attendance.noticeboard-confirmation-items.image', $item->getKey()),
            ])->values()->all(),
        ];
    }

    /**
     * Confirm exactly the complete, immutable list on its business day.
     *
     * @param list<int> $itemIds
     */
    public function confirm(User $actor, Store $store, int $confirmationId, array $itemIds): void
    {
        DB::transaction(function () use ($actor, $store, $confirmationId, $itemIds): void {
            $store = $this->lockStore($actor, $store);
            $confirmation = $this->query($actor, $store)->whereKey($confirmationId)->lockForUpdate()->firstOrFail();
            if ($confirmation->getDate() !== CarbonImmutable::now('Europe/Prague')->toDateString()) {
                Thrower::default()->message('confirmation', \__('This confirmation belongs to a different day.'))->throw();
            }
            $expected = $confirmation->getItems()->map(static fn(NoticeboardConfirmationItem $item): int => $item->getKey())->all();
            \sort($expected);
            \sort($itemIds);
            if ($expected === [] || $itemIds !== $expected) {
                Thrower::default()->message('item_ids', \__('Read and select every card before confirming.'))->throw();
            }
            if ($confirmation->getConfirmedAt() === null) {
                $confirmation->update(['confirmed_by_user_id' => $actor->getKey(), 'confirmed_at' => CarbonImmutable::now('UTC')]);
            }
        });
    }

    /**
     * Read confirmation indicators for a page of cards in a bounded batch.
     *
     * @param Collection<int, NoticeboardCard> $cards
     *
     * @return array<int, array{worker_name: string, confirmed_at: string}>
     */
    public function confirmedCards(User $actor, Store $store, Collection $cards): array
    {
        $this->authorize($actor, $store);
        $dates = [];
        foreach ($cards as $card) {
            if ($card->getDisplayOn() !== null) {
                $dates[$card->getKey()] = $card->getDisplayOn();
            }
        }
        if ($dates === []) {
            return [];
        }
        $items = NoticeboardConfirmationItem::query()->whereIn('noticeboard_card_id', \array_keys($dates))
            ->whereIn('confirmation_id', $this->query($actor, $store)->select('id')->whereNotNull('confirmed_at')->whereIn('date', \array_values($dates)))
            ->with('confirmation')->get();
        $result = [];
        foreach ($items as $item) {
            $cardId = $item->getNoticeboardCardId();
            $confirmation = $item->getConfirmation();
            $confirmedAt = $confirmation->getConfirmedAt();
            if ($cardId !== null && ($dates[$cardId] ?? null) === $confirmation->getDate() && $confirmedAt !== null) {
                $result[$cardId] = ['worker_name' => $confirmation->getWorkerName(), 'confirmed_at' => $confirmedAt->toIso8601String()];
            }
        }

        return $result;
    }

    /**
     * Resolve a private snapshot image inside the authorized company and store.
     */
    public function imageItem(User $actor, Store $store, int $itemId): NoticeboardConfirmationItem
    {
        $this->authorize($actor, $store);

        return NoticeboardConfirmationItem::query()->whereKey($itemId)
            ->whereIn('confirmation_id', $this->query($actor, $store)->select('id'))->firstOrFail();
    }

    /**
     * @return Builder<NoticeboardConfirmation>
     */
    private function query(User $actor, Store $store): Builder
    {
        $query = NoticeboardConfirmation::query();
        NoticeboardConfirmation::scopeForUser($query, $actor->resolveScopeUser());
        NoticeboardConfirmation::scopeForStore($query, $store->getKey());

        return $query;
    }

    /**
     * Enforce company and assigned retail-store boundaries.
     */
    private function authorize(User $actor, Store $store): void
    {
        if (!$store->isActive() || $store->isWarehouse() || $store->getUserId() !== $actor->resolveScopeUser()->getKey() ||
            (!$actor->isAdmin() && $actor->getAssignedStoreId() !== $store->getKey())) {
            \abort(403);
        }
    }

    /**
     * Serialize arrivals, card changes and confirmations on the store row.
     */
    private function lockStore(User $actor, Store $store): Store
    {
        $store = Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail();
        $this->authorize($actor, $store);

        return $store;
    }
}
