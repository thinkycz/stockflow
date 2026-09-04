<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Enums\OperationalActivityTypeEnum;
use App\Enums\StockMovementClassificationEnum;
use App\Exceptions\InventoryRevisionConflictException;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Support\OperationalActivityService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class InventorySessionService
{
    /**
     * Create the service.
     */
    public function __construct(
        private readonly StockMovementService $movementService,
        private readonly InventoryReadService $readService,
    ) {
    }

    /**
     * Start or return the single active draft for a store.
     */
    public function startDraft(User $user, Store $store): InventorySession
    {
        $owner = $user->resolveScopeUser();
        $this->authoriseStore($user, $store);

        return DB::transaction(function () use ($user, $owner, $store): InventorySession {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $this->authoriseStore($user, $store);
            $existing = InventorySession::query()
                ->where('user_id', $owner->getKey())
                ->where('active_store_key', $store->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing instanceof InventorySession) {
                return $existing;
            }

            $snapshot = StoreItem::query()
                ->where('store_id', $store->getKey())
                ->orderBy('item_id')
                ->pluck('quantity', 'item_id')
                ->map(static fn(mixed $quantity): string => BigDecimal::of((string) Typer::assertScalar($quantity))->toScale(3)->__toString())
                ->all();

            return InventorySession::query()->create([
                'user_id' => $owner->getKey(),
                'store_id' => $store->getKey(),
                'active_store_key' => $store->getKey(),
                'created_by' => $user->getKey(),
                'status' => 'draft',
                'started_at' => Carbon::now(),
                'counted_at' => null,
                'opening_snapshot' => $snapshot,
            ]);
        }, 3);
    }

    /**
     * Find the active draft visible to the user.
     */
    public function activeDraft(User $user, Store $store): InventorySession|null
    {
        $this->authoriseStore($user, $store);

        return InventorySession::query()
            ->where('user_id', $user->resolveScopeUser()->getKey())
            ->where('active_store_key', $store->getKey())
            ->with('items')
            ->first();
    }

    /**
     * Save exact counted values only against their acknowledged server revision.
     */
    public function saveDraftRow(User $user, InventorySession $session, InventoryDraftRowInput $input): InventorySessionItem
    {
        $this->authoriseSession($user, $session);

        return DB::transaction(function () use ($user, $session, $input): InventorySessionItem {
            $lockedSession = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $this->authoriseSession($user, $lockedSession);
            if (!$lockedSession->isDraft()) {
                $this->fail(['inventory' => \__('Only an open inventory draft can be edited.')]);
            }

            $itemId = $input->itemId;
            $quantity = $this->draftQuantity($input->quantity);
            $expectedRevision = $input->expectedRevision;
            if ($quantity->isNegative() || $expectedRevision < 0) {
                $this->fail(['quantity' => \__('A non-negative quantity and version are required.')]);
            }

            $item = Item::query()->where('user_id', $lockedSession->getUserId())->whereKey($itemId)->first();
            if (!$item instanceof Item) {
                $this->fail(['item_id' => \__('Item not found.')]);
            }

            $existing = InventorySessionItem::query()
                ->where('session_id', $lockedSession->getKey())
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->first();
            if ($expectedRevision !== ($existing?->getRevision() ?? 0)) {
                throw new InventoryRevisionConflictException($existing);
            }

            $countedAt = Carbon::now();
            $expected = $this->decimal($this->readService->currentQuantity($lockedSession->getStore(), $item));
            $difference = $quantity->minus($expected);
            $classification = $this->resolveDraftClassification($difference, $input->classification);
            $snapshot = Typer::assertArray($lockedSession->getAttribute('opening_snapshot') ?? []);

            return InventorySessionItem::query()->updateOrCreate(
                ['session_id' => $lockedSession->getKey(), 'item_id' => $itemId],
                [
                    'quantity' => (string) $quantity,
                    'counted_at' => $countedAt,
                    'opening_quantity' => (string) $this->decimal($snapshot[(string) $itemId] ?? $snapshot[$itemId] ?? 0),
                    'client_version' => $expectedRevision + 1,
                    'expected_quantity' => (string) $expected,
                    'quantity_difference' => (string) $difference,
                    'classification' => $classification?->value,
                    'observation_started_at' => $this->readService->previousCountedAt($lockedSession->getStore(), $item, $countedAt),
                    'note' => $input->note,
                ],
            );
        }, 3);
    }

    /**
     * Close a draft and apply only its row-level physical differences.
     */
    public function closeDraft(User $user, InventorySession $session, Carbon|null $countedAt = null): InventorySession
    {
        $this->authoriseSession($user, $session);
        $owner = $user->resolveScopeUser();
        $countedAt ??= Carbon::now();

        return DB::transaction(function () use ($user, $session, $owner, $countedAt): InventorySession {
            $session = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if (!$session->isDraft()) {
                $this->fail(['inventory' => \__('Only an open inventory draft can be closed.')]);
            }

            $rows = $session->items()->with('item')->orderBy('item_id')->lockForUpdate()->get();
            $reconciliationRows = [];
            foreach ($rows as $row) {
                $item = $row->getItem();
                $difference = $this->decimal(Typer::assertScalar($row->getQuantityDifference()));
                $storeItem = StoreItem::query()->firstOrCreate(
                    ['store_id' => $session->getStore()->getKey(), 'item_id' => $item->getKey()],
                    ['quantity' => 0],
                );
                $storeItem = StoreItem::query()->whereKey($storeItem->getKey())->lockForUpdate()->firstOrFail();
                $before = $this->decimal($storeItem->getAttribute('quantity'));
                $after = $before->plus($difference);
                if ($after->isNegative()) {
                    $this->fail(['inventory' => \__('Inventory reconciliation would make stock negative.')]);
                }
                $storeItem->update(['quantity' => (string) $after]);

                $classification = $row->getClassification();
                if (!$difference->isZero() && $classification instanceof StockMovementClassificationEnum) {
                    $reconciliationRows[] = [
                        'session_item' => $row, 'item' => $item, 'expected' => (string) $before, 'counted' => (string) $after,
                        'difference' => (string) $difference, 'classification' => $classification,
                        'observation_started_at' => $row->getObservationStartedAt(),
                    ];
                }
            }

            $now = Carbon::now();
            $session->update(['status' => 'closed', 'active_store_key' => null, 'counted_at' => $countedAt, 'closed_at' => $now]);
            $this->movementService->createInventoryReconciliation($session, $owner, $reconciliationRows);
            $this->notifyInventory($user, $session, $rows->count(), \count($reconciliationRows));

            return $session;
        }, 3);
    }

    /**
     * Cancel a draft without posting inventory changes.
     */
    public function cancelDraft(User $user, InventorySession $session): void
    {
        $this->authoriseSession($user, $session);
        DB::transaction(function () use ($user, $session): void {
            $session = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $this->authoriseSession($user, $session);
            if ($session->getStatus() === 'cancelled') {
                return;
            }
            if (!$session->isDraft()) {
                $this->fail(['inventory' => \__('Only an open inventory draft can be edited.')]);
            }
            $session->update(['status' => 'cancelled', 'active_store_key' => null, 'cancelled_at' => Carbon::now()]);
        }, 3);
    }

    /**
     * Create a new inventory session for the given store.
     *
     * Persists a session header in `inventory_sessions`, one row per
     * item in `inventory_session_items`, and upserts the matching
     * `store_items` row so the application keeps a single source of
     * truth for the current quantity.
     *
     * Rows whose `quantity` is `null` are skipped entirely: no
     * `inventory_session_items` row is written and the existing
     * `store_items.quantity` is left untouched. A row with `quantity`
     * of `0` (or any other integer) is persisted as recorded and
     * applied to the on-hand count.
     *
     * For limited users, the session is attributed to the parent
     * (admin) account so the row appears in the data the admin owns.
     * `created_by` keeps the actual user who entered the count.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function createSession(User $user, Store $store, array $rows, string|null $note = null): InventorySession
    {
        $now = Carbon::now();
        $owner = $user->isAdmin() ? $user : $this->resolveOwner($user);

        return DB::transaction(function () use ($user, $owner, $store, $rows, $note, $now): InventorySession {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $this->authoriseStore($user, $store);

            $session = InventorySession::query()->create([
                'user_id' => $owner->getKey(),
                'store_id' => $store->getKey(),
                'created_by' => $user->getKey(),
                'counted_at' => $now,
                'note' => $note,
            ]);

            $reconciliationRows = [];

            foreach ($rows as $row) {
                $payload = Typer::assertArray($row);
                $itemId = Typer::parseInt($payload['item_id'] ?? 0);

                if ($itemId <= 0) {
                    continue;
                }

                if (!\array_key_exists('quantity', $payload) || $payload['quantity'] === null) {
                    continue;
                }

                $quantity = $this->decimal($payload['quantity']);
                $rowNote = Typer::parseNullableString($payload['note'] ?? null);

                $itemQuery = Item::query();
                Item::scopeForUser($itemQuery, $owner);
                $item = $itemQuery->whereKey($itemId)->first();

                if (!$item instanceof Item) {
                    continue;
                }

                $storeItem = StoreItem::query()->firstOrCreate(
                    ['store_id' => $store->getKey(), 'item_id' => $item->getKey()],
                    ['quantity' => 0],
                );
                $storeItem = StoreItem::query()->whereKey($storeItem->getKey())->lockForUpdate()->first();

                if (!$storeItem instanceof StoreItem) {
                    continue;
                }

                $expected = $this->decimal($storeItem->getAttribute('quantity'));
                $difference = $quantity->minus($expected);
                $classification = $this->resolveClassification(
                    $difference,
                    Typer::parseNullableString($payload['classification'] ?? null),
                );
                $observationStartedAt = $this->readService->previousCountedAt($store, $item, $now);

                $sessionItem = InventorySessionItem::query()->create([
                    'session_id' => $session->getKey(),
                    'item_id' => $item->getKey(),
                    'quantity' => (string) $quantity,
                    'expected_quantity' => (string) $expected,
                    'quantity_difference' => (string) $difference,
                    'classification' => $classification?->value,
                    'observation_started_at' => $observationStartedAt,
                    'note' => $rowNote,
                ]);

                $storeItem->update(['quantity' => (string) $quantity]);

                if (!$difference->isZero() && $classification instanceof StockMovementClassificationEnum) {
                    $reconciliationRows[] = [
                        'session_item' => $sessionItem,
                        'item' => $item,
                        'expected' => (string) $expected,
                        'counted' => (string) $quantity,
                        'difference' => (string) $difference,
                        'classification' => $classification,
                        'observation_started_at' => $observationStartedAt,
                    ];
                }
            }

            $this->movementService->createInventoryReconciliation($session, $owner, $reconciliationRows);
            $this->notifyInventory($user, $session, $session->items()->count(), \count($reconciliationRows));

            return $session;
        });
    }

    /**
     * Dispatch one finalized inventory activity without duplicating its reconciliation.
     */
    private function notifyInventory(User $user, InventorySession $session, int $countedRows, int $differenceRows): void
    {
        OperationalActivityService::dispatch(
            OperationalActivityTypeEnum::INVENTORY_SAVED,
            $user,
            Carbon::now('UTC')->toIso8601String(),
            Resolver::resolveUrlGenerator()->route('inventory-counts.show', ['session' => $session->getKey()]),
            [['store' => $session->getStore(), 'perspective' => null]],
            [
                'Slack inventory number' => '#' . $session->getKey(),
                'Slack counted rows' => (string) $countedRows,
                'Slack difference rows' => (string) $differenceRows,
            ],
        );
    }

    /**
     * The admin (parent) account that owns the inventory data for a
     * limited user. Falls back to the user itself when the parent is
     * missing.
     */
    private function resolveOwner(User $user): User
    {
        $parentId = $user->getParentUserId();

        if ($parentId !== null) {
            $parent = User::query()->whereKey($parentId)->first();

            if ($parent instanceof User) {
                return $parent;
            }
        }

        return $user;
    }

    /**
     * Enforce owner and assigned-store access.
     */
    private function authoriseStore(User $user, Store $store): void
    {
        if ($store->getUserId() !== $user->resolveScopeUser()->getKey() || !$store->isActive()) {
            \abort(404);
        }

        if (!$user->isAdmin() && $user->getAssignedStoreId() !== $store->getKey()) {
            \abort(403);
        }
    }

    /**
     * Enforce access to the draft's store and owning company.
     */
    private function authoriseSession(User $user, InventorySession $session): void
    {
        if ($session->getUserId() !== $user->resolveScopeUser()->getKey()) {
            \abort(404);
        }

        $this->authoriseStore($user, $session->getStore());
    }

    /**
     * @param array<string, array<array-key, mixed>|string> $messages
     */
    private function fail(array $messages): never
    {
        $validator = Resolver::resolveValidatorFactory()->make([], []);
        $thrower = new Thrower($validator);

        foreach ($messages as $key => $message) {
            $thrower->message($key, $message);
        }

        $thrower->throw();
    }

    /**
     * Resolve and validate a classification for an inventory difference.
     */
    private function resolveClassification(BigDecimal $difference, string|null $requested): StockMovementClassificationEnum|null
    {
        if ($difference->isZero()) {
            return null;
        }

        $classification = $requested === null
            ? ($difference->isNegative() ? StockMovementClassificationEnum::CONSUMPTION : StockMovementClassificationEnum::INVENTORY_CORRECTION)
            : StockMovementClassificationEnum::from($requested);

        if (
            ($difference->isNegative() && !$classification->supportsNegativeDifference()) ||
            ($difference->isPositive() && !$classification->supportsPositiveDifference())
        ) {
            $validator = Resolver::resolveValidatorFactory()->make([], []);
            (new Thrower($validator))
                ->message('rows', \__('The selected inventory reason does not match the quantity difference.'))
                ->throw();
        }

        return $classification;
    }

    /**
     * Keep draft autosave non-blocking when the expected stock changed after
     * the browser selected a reason or when a stale client sends an unknown
     * classification.
     */
    private function resolveDraftClassification(BigDecimal $difference, string|null $requested): StockMovementClassificationEnum|null
    {
        if ($difference->isZero()) {
            return null;
        }

        $fallback = $difference->isNegative()
            ? StockMovementClassificationEnum::CONSUMPTION
            : StockMovementClassificationEnum::INVENTORY_CORRECTION;
        $classification = $requested === null
            ? $fallback
            : StockMovementClassificationEnum::tryFrom($requested) ?? $fallback;

        if (
            ($difference->isNegative() && !$classification->supportsNegativeDifference()) ||
            ($difference->isPositive() && !$classification->supportsPositiveDifference())
        ) {
            return $fallback;
        }

        return $classification;
    }

    /**
     * Parse an exact stock quantity at the canonical scale.
     */
    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) Typer::assertScalar($value))->toScale(3, RoundingMode::Unnecessary);
    }

    /**
     * Normalize user-entered draft quantities instead of rejecting harmless
     * precision beyond the canonical three decimal places.
     */
    private function draftQuantity(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) Typer::assertScalar($value))->toScale(3, RoundingMode::HalfUp);
    }
}
