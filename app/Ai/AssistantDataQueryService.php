<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\AttendanceSession;
use App\Models\ChecklistDay;
use App\Models\FinancialRecurringExpense;
use App\Models\FinancialReport;
use App\Models\GiftVoucher;
use App\Models\InventorySession;
use App\Models\Item;
use App\Models\NoticeboardCard;
use App\Models\PayrollReport;
use App\Models\Recipe;
use App\Models\RecipeTestSession;
use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\ShiftRequestMonthLock;
use App\Models\ShiftShareLink;
use App\Models\Statement;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

final class AssistantDataQueryService
{
    /**
     * Execute one fixed-domain, tenant-scoped, bounded live-data query.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array{resource: string, count: int, limit: int, records: list<array<string, mixed>>}
     */
    public function query(User $actor, array $arguments): array
    {
        $resource = \is_string($arguments['resource'] ?? null) ? $arguments['resource'] : '';
        $search = \is_string($arguments['search'] ?? null) && \mb_trim($arguments['search']) !== ''
            ? \mb_trim($arguments['search'])
            : null;
        $storeId = \is_int($arguments['store_id'] ?? null) ? $arguments['store_id'] : null;
        $limit = \min(
            \max(\is_int($arguments['limit'] ?? null) ? $arguments['limit'] : 20, 1),
            Config::inject()->assertInt('ai.assistant.tool_result_limit'),
        );
        $records = match ($resource) {
            'stores' => $this->stores($actor, $search, $limit),
            'settings' => $this->settings($actor),
            'items' => $this->items($actor, $search, $limit),
            'inventory' => $this->inventory($actor, $search, $storeId, $limit),
            'inventory_counts' => $this->inventoryCounts($actor, $storeId, $limit),
            'stock_movements' => $this->movements($actor, $search, $storeId, $limit),
            'statements' => $this->statements($actor, $storeId, $limit),
            'workers' => $this->workers($actor, $search, $limit),
            'attendance' => $this->attendance($actor, $storeId, $limit),
            'shifts' => $this->shifts($actor, $storeId, $limit),
            'shift_requests' => $this->shiftRequests($actor, $storeId, $limit),
            'shift_share_links' => $this->shiftShareLinks($actor, $storeId, $limit),
            'checklists' => $this->checklists($actor, $storeId, $limit),
            'noticeboard' => $this->noticeboard($actor, $search, $storeId, $limit),
            'recipes' => $this->recipes($actor, $search, $limit),
            'recipe_tests' => $this->recipeTests($actor, $limit),
            'payroll' => $this->payroll($actor, $storeId, $limit),
            'income_expenses' => $this->financialReports($actor, $storeId, $limit),
            'recurring_expenses' => $this->recurringExpenses($actor, $search, $storeId, $limit),
            'gift_vouchers' => $this->vouchers($actor, $limit),
            'users' => $this->users($actor, $search, $limit),
            default => throw new InvalidArgumentException('Unknown Stockflow data resource.'),
        };

        return [
            'resource' => $resource,
            'count' => \count($records),
            'limit' => $limit,
            'records' => \array_values($records),
        ];
    }

    /**
     * Query owned stores.
     *
     * @return array<int, array<string, mixed>>
     */
    private function stores(User $actor, string|null $search, int $limit): array
    {
        $query = Store::query();
        Store::scopeForUser($query, $actor->resolveScopeUser());
        $this->search($query, Store::class, $search);

        return Store::querySelect($query)->orderBy('name')->limit($limit)->get()
            ->map(static fn(Store $store): array => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'address' => $store->getAddress(),
                'status' => $store->getStatus()->value,
                'is_warehouse' => $store->isWarehouse(),
                'url' => Resolver::resolveUrlGenerator()->route('stores.show', $store->getKey()),
            ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function settings(User $actor): array
    {
        return [[
            'user_id' => $actor->getKey(),
            'email' => $actor->getEmail(),
            'locale' => $actor->getLocale(),
            'company_slack_channel' => $actor->getCompanySlackChannel(),
            'active_store_id' => $actor->getActiveStoreId(),
            'url' => Resolver::resolveUrlGenerator()->route('settings.show'),
        ]];
    }

    /**
     * Query catalog items.
     *
     * @return array<int, array<string, mixed>>
     */
    private function items(User $actor, string|null $search, int $limit): array
    {
        $query = Item::query();
        Item::scopeForUser($query, $actor->resolveScopeUser());
        $this->search($query, Item::class, $search);

        return Item::querySelect($query)->orderBy('title')->limit($limit)->get()
            ->map(static fn(Item $item): array => [
                'id' => $item->getKey(),
                'title' => $item->getTitle(),
                'sku' => $item->getSku(),
                'unit' => $item->getUnit(),
                'purchase_price' => $item->getPurchasePrice(),
                'total_quantity' => $item->getTotalQuantity(),
                'warehouse_quantity' => $item->getWarehouseQuantity(),
                'url' => Resolver::resolveUrlGenerator()->route('items.show', $item->getKey()),
            ])->values()->all();
    }

    /**
     * Query per-store inventory balances.
     *
     * @return array<int, array<string, mixed>>
     */
    private function inventory(User $actor, string|null $search, int|null $storeId, int $limit): array
    {
        $query = StoreItem::query()
            ->with(['store', 'item'])
            ->whereHas('store', static function (Builder $query) use ($actor): void {
                $query->where('user_id', $actor->resolveScopeUser()->getKey());
            });

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        if ($search !== null) {
            StoreItem::scopeSearch($query, $search);
        }

        return StoreItem::querySelect($query)->orderBy('item_id')->limit($limit)->get()
            ->map(static function (StoreItem $row): array {
                $store = $row->getStore();
                $item = $row->getItem();

                return [
                    'store_id' => $store->getKey(),
                    'store_name' => $store->getName(),
                    'item_id' => $item->getKey(),
                    'item_title' => $item->getTitle(),
                    'sku' => $item->getSku(),
                    'unit' => $item->getUnit(),
                    'quantity' => $row->getQuantity(),
                    'item_url' => Resolver::resolveUrlGenerator()->route('items.show', $item->getKey()),
                ];
            })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function inventoryCounts(User $actor, int|null $storeId, int $limit): array
    {
        $query = InventorySession::query()->with(['items', 'store']);
        InventorySession::scopeForUser($query, $actor->resolveScopeUser());

        if ($storeId !== null) {
            InventorySession::scopeForStore($query, $storeId);
        }

        return InventorySession::querySelect($query)->orderByDesc('started_at')->limit($limit)->get()
            ->map(static function (InventorySession $session): array {
                $store = $session->getStore();

                return [
                    'id' => $session->getKey(),
                    'store_id' => $store->getKey(),
                    'status' => $session->getStatus(),
                    'started_at' => $session->getStartedAt()->toJSON(),
                    'counted_at' => $session->getCountedAt()->toJSON(),
                    'rows_count' => $session->getItems()->count(),
                    'note' => $session->getNote(),
                    'url' => Resolver::resolveUrlGenerator()->route('inventory-counts.index', ['store_id' => $store->getKey()]),
                ];
            })->values()->all();
    }

    /**
     * Query recent stock movements.
     *
     * @return array<int, array<string, mixed>>
     */
    private function movements(User $actor, string|null $search, int|null $storeId, int $limit): array
    {
        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $actor->resolveScopeUser());
        $this->search($query, StockMovement::class, $search);

        if ($storeId !== null) {
            $query->where(static function (Builder $query) use ($storeId): void {
                $query->where('store_id', $storeId)->orWhere('source_store_id', $storeId);
            });
        }

        return StockMovement::querySelect($query)->orderByDesc('occurred_at')->limit($limit)->get()
            ->map(static fn(StockMovement $movement): array => [
                'id' => $movement->getKey(),
                'number' => $movement->getNumber(),
                'type' => $movement->getType()->value,
                'store_id' => $movement->getStoreId(),
                'source_store_id' => $movement->getSourceStoreId(),
                'occurred_at' => $movement->getOccurredAt()->toJSON(),
                'items_count' => $movement->getItemsCount(),
                'total_quantity' => $movement->getTotalQuantity(),
                'total_value' => $movement->getTotalValue(),
                'reversed_at' => $movement->getReversedAt()?->toJSON(),
                'url' => Resolver::resolveUrlGenerator()->route('stock-movements.show', $movement->getKey()),
            ])->values()->all();
    }

    /**
     * Query monthly statements.
     *
     * @return array<int, array<string, mixed>>
     */
    private function statements(User $actor, int|null $storeId, int $limit): array
    {
        $query = Statement::query();
        Statement::scopeForUser($query, $actor->resolveScopeUser());

        if ($storeId !== null) {
            Statement::scopeForStore($query, $storeId);
        }

        return Statement::querySelect($query)->orderByDesc('year')->orderByDesc('month')->limit($limit)->get()
            ->map(static fn(Statement $statement): array => [
                'id' => $statement->getKey(),
                'store_id' => $statement->getStoreId(),
                'year' => $statement->getYear(),
                'month' => $statement->getMonth(),
                'url' => Resolver::resolveUrlGenerator()->route('statements.index', ['store_id' => $statement->getStoreId()]),
            ])->values()->all();
    }

    /**
     * Query workers.
     *
     * @return array<int, array<string, mixed>>
     */
    private function workers(User $actor, string|null $search, int $limit): array
    {
        $query = Worker::query();
        Worker::scopeForUser($query, $actor->resolveScopeUser());
        $this->search($query, Worker::class, $search);

        return Worker::querySelect($query)->orderBy('last_name')->orderBy('first_name')->limit($limit)->get()
            ->map(static fn(Worker $worker): array => [
                'id' => $worker->getKey(),
                'name' => $worker->getFullName(),
                'hourly_rate' => $worker->getHourlyRate(),
                'attendance_rating_enabled' => $worker->isAttendanceRatingEnabled(),
                'url' => Resolver::resolveUrlGenerator()->route('workers.edit', $worker->getKey()),
            ])->values()->all();
    }

    /**
     * Query attendance sessions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function attendance(User $actor, int|null $storeId, int $limit): array
    {
        $query = AttendanceSession::query();
        AttendanceSession::scopeForUser($query, $actor->resolveScopeUser());

        if ($storeId !== null) {
            AttendanceSession::scopeForStore($query, $storeId);
        }

        return AttendanceSession::querySelect($query)->orderByDesc('started_at')->limit($limit)->get()
            ->map(static fn(AttendanceSession $session): array => [
                'id' => $session->getKey(),
                'store_id' => $session->getStoreId(),
                'worker_id' => $session->getWorkerId(),
                'shift_id' => $session->getShiftId(),
                'started_at' => $session->getStartedAt()->toJSON(),
                'ended_at' => $session->getEndedAt()?->toJSON(),
                'voided_at' => $session->getVoidedAt()?->toJSON(),
                'url' => Resolver::resolveUrlGenerator()->route('attendance.index', ['store_id' => $session->getStoreId()]),
            ])->values()->all();
    }

    /**
     * Query scheduled shifts.
     *
     * @return array<int, array<string, mixed>>
     */
    private function shifts(User $actor, int|null $storeId, int $limit): array
    {
        $query = Shift::query();
        Shift::scopeForUser($query, $actor->resolveScopeUser());

        if ($storeId !== null) {
            Shift::scopeForStore($query, $storeId);
        }

        return Shift::querySelect($query)->orderByDesc('date')->orderBy('start_time')->limit($limit)->get()
            ->map(static fn(Shift $shift): array => [
                'id' => $shift->getKey(),
                'store_id' => $shift->getStoreId(),
                'worker_id' => $shift->getWorkerId(),
                'date' => $shift->getDate(),
                'start_time' => $shift->getStartTime(),
                'end_time' => $shift->getEndTime(),
                'duration_minutes' => $shift->getDurationMinutes(),
                'url' => Resolver::resolveUrlGenerator()->route('shifts.index', ['store_id' => $shift->getStoreId()]),
            ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function shiftRequests(User $actor, int|null $storeId, int $limit): array
    {
        $requests = ShiftRequest::query()->where('user_id', $actor->resolveScopeUser()->getKey());

        if ($storeId !== null) {
            $requests->where('store_id', $storeId);
        }

        $records = $requests->orderByDesc('date')->limit($limit)->get()->map(static fn(ShiftRequest $request): array => [
            'type' => 'request',
            'id' => $request->getKey(),
            'store_id' => $request->getStoreId(),
            'worker_id' => $request->getWorkerId(),
            'date' => $request->getDate(),
            'start_time' => $request->getStartTimeShort(),
            'end_time' => $request->getEndTimeShort(),
            'url' => Resolver::resolveUrlGenerator()->route('shifts.index', ['store_id' => $request->getStoreId()]),
        ])->values()->all();

        if ($limit > \count($records)) {
            $locks = ShiftRequestMonthLock::query()->where('user_id', $actor->resolveScopeUser()->getKey());

            if ($storeId !== null) {
                $locks->where('store_id', $storeId);
            }

            foreach (ShiftRequestMonthLock::querySelect($locks)->orderByDesc('year')->orderByDesc('month')->limit($limit - \count($records))->get() as $lock) {
                $records[] = [
                    'type' => 'month_lock',
                    'id' => $lock->getKey(),
                    'store_id' => $lock->getStoreId(),
                    'year' => $lock->getYear(),
                    'month' => $lock->getMonth(),
                    'locked_at' => $lock->getLockedAt()->toJSON(),
                    'url' => Resolver::resolveUrlGenerator()->route('shifts.index', ['store_id' => $lock->getStoreId()]),
                ];
            }
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function shiftShareLinks(User $actor, int|null $storeId, int $limit): array
    {
        $query = ShiftShareLink::query();
        ShiftShareLink::scopeForUser($query, $actor->resolveScopeUser());

        if ($storeId !== null) {
            ShiftShareLink::scopeForStore($query, $storeId);
        }

        return ShiftShareLink::querySelect($query)->orderByDesc('created_at')->limit($limit)->get()
            ->map(static fn(ShiftShareLink $link): array => [
                'id' => $link->getKey(),
                'store_id' => $link->getStoreId(),
                'name' => $link->getName(),
                'url' => Resolver::resolveUrlGenerator()->route('public-shifts.index', ['token' => $link->getToken()]),
            ])->values()->all();
    }

    /**
     * Query checklist days and completion counts.
     *
     * @return array<int, array<string, mixed>>
     */
    private function checklists(User $actor, int|null $storeId, int $limit): array
    {
        $query = ChecklistDay::query()->with('items');
        ChecklistDay::scopeForUser($query, $actor->resolveScopeUser());

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return ChecklistDay::querySelect($query)->orderByDesc('date')->limit($limit)->get()
            ->map(static function (ChecklistDay $day): array {
                $items = $day->getItems();

                return [
                    'id' => $day->getKey(),
                    'store_id' => $day->getStoreId(),
                    'date' => $day->getDate()->toDateString(),
                    'excused' => $day->isExcused(),
                    'item_count' => $items->count(),
                    'completed_count' => $items->filter(static fn($item): bool => $item->isCompleted())->count(),
                    'url' => Resolver::resolveUrlGenerator()->route('checklists.index', ['store_id' => $day->getStoreId()]),
                ];
            })->values()->all();
    }

    /**
     * Query noticeboard card metadata without binary content.
     *
     * @return array<int, array<string, mixed>>
     */
    private function noticeboard(User $actor, string|null $search, int|null $storeId, int $limit): array
    {
        $query = NoticeboardCard::query();
        NoticeboardCard::scopeForUser($query, $actor->resolveScopeUser());
        $this->search($query, NoticeboardCard::class, $search);

        if ($storeId !== null) {
            NoticeboardCard::scopeForStore($query, $storeId);
        }

        return NoticeboardCard::querySelect($query)->orderByDesc('updated_at')->limit($limit)->get()
            ->map(static fn(NoticeboardCard $card): array => [
                'id' => $card->getKey(),
                'store_id' => $card->getStoreId(),
                'title' => $card->getTitle(),
                'label' => $card->getLabel()->value,
                'expires_at' => $card->getExpiresAt()?->toJSON(),
                'has_image' => $card->getImagePath() !== null,
                'url' => Resolver::resolveUrlGenerator()->route('dashboard', ['store_id' => $card->getStoreId()]),
            ])->values()->all();
    }

    /**
     * Query recipes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recipes(User $actor, string|null $search, int $limit): array
    {
        $query = Recipe::query();
        Recipe::scopeForUser($query, $actor->resolveScopeUser());
        $this->search($query, Recipe::class, $search);

        return Recipe::querySelect($query)->orderBy('position')->limit($limit)->get()
            ->map(static fn(Recipe $recipe): array => [
                'id' => $recipe->getKey(),
                'category_id' => $recipe->getCategoryId(),
                'name' => $recipe->getName(),
                'note' => $recipe->getNote(),
                'archived' => $recipe->isArchived(),
                'url' => Resolver::resolveUrlGenerator()->route('recipes.show', $recipe->getKey()),
            ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recipeTests(User $actor, int $limit): array
    {
        return RecipeTestSession::query()->where('user_id', $actor->resolveScopeUser()->getKey())
            ->orderByDesc('id')->limit($limit)->get()
            ->map(static fn(RecipeTestSession $session): array => [
                'id' => $session->getKey(),
                'worker_name' => $session->getWorkerName(),
                'actor_user_id' => $session->getActorUserId(),
                'score' => $session->getScore(),
                'submitted_at' => $session->getSubmittedAt()?->toJSON(),
                'attempts_count' => $session->getAttempts()->count(),
                'url' => Resolver::resolveUrlGenerator()->route('recipe-test-results.index'),
            ])->values()->all();
    }

    /**
     * Query payroll report lifecycle state.
     *
     * @return array<int, array<string, mixed>>
     */
    private function payroll(User $actor, int|null $storeId, int $limit): array
    {
        $query = PayrollReport::query();
        PayrollReport::scopeForUser($query, $actor->resolveScopeUser());

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return PayrollReport::querySelect($query)->orderByDesc('year')->orderByDesc('month')->limit($limit)->get()
            ->map(static fn(PayrollReport $report): array => [
                'id' => $report->getKey(),
                'store_id' => $report->getStoreId(),
                'year' => $report->getYear(),
                'month' => $report->getMonth(),
                'status' => $report->getStatus()->value,
                'closed_at' => $report->getClosedAt()?->toJSON(),
                'url' => Resolver::resolveUrlGenerator()->route('payroll.index', ['store_id' => $report->getStoreId()]),
            ])->values()->all();
    }

    /**
     * Query income and expense report lifecycle state.
     *
     * @return array<int, array<string, mixed>>
     */
    private function financialReports(User $actor, int|null $storeId, int $limit): array
    {
        $query = FinancialReport::query();
        FinancialReport::scopeForUser($query, $actor->resolveScopeUser());

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return FinancialReport::querySelect($query)->orderByDesc('year')->orderByDesc('month')->limit($limit)->get()
            ->map(static fn(FinancialReport $report): array => [
                'id' => $report->getKey(),
                'store_id' => $report->getStoreId(),
                'year' => $report->getYear(),
                'month' => $report->getMonth(),
                'status' => $report->getStatus()->value,
                'closed_at' => $report->getClosedAt()?->toJSON(),
                'url' => Resolver::resolveUrlGenerator()->route('income-expenses.index', ['store_id' => $report->getStoreId()]),
            ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recurringExpenses(User $actor, string|null $search, int|null $storeId, int $limit): array
    {
        $query = FinancialRecurringExpense::query()->with('versions');
        FinancialRecurringExpense::scopeForUser($query, $actor->resolveScopeUser());

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $this->search($query, FinancialRecurringExpense::class, $search);

        return FinancialRecurringExpense::querySelect($query)->orderByDesc('starts_on')->limit($limit)->get()
            ->map(static function (FinancialRecurringExpense $expense): array {
                $version = $expense->getVersions()->last();

                return [
                    'id' => $expense->getKey(),
                    'store_id' => $expense->getStoreId(),
                    'starts_on' => $expense->getStartsOn(),
                    'ends_before' => $expense->getEndsBefore(),
                    'label' => $version?->getLabel(),
                    'amount' => $version?->getAmount(),
                    'due_day' => $version?->getDueDay(),
                    'url' => Resolver::resolveUrlGenerator()->route('income-expenses.recurring-expenses.index', ['store_id' => $expense->getStoreId()]),
                ];
            })->values()->all();
    }

    /**
     * Query voucher lifecycle state without exposing codes or hashes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function vouchers(User $actor, int $limit): array
    {
        $query = GiftVoucher::query();
        GiftVoucher::scopeForUser($query, $actor->resolveScopeUser());

        return GiftVoucher::querySelect($query)->orderByDesc('created_at')->limit($limit)->get()
            ->map(static fn(GiftVoucher $voucher): array => [
                'id' => $voucher->getKey(),
                'status' => $voucher->getEffectiveStatus()->value,
                'redeemed_at' => $voucher->getRedeemedAt()?->toJSON(),
                'redeemed_store_id' => $voucher->getRedeemedStoreId(),
                'url' => Resolver::resolveUrlGenerator()->route('gift-vouchers.index'),
            ])->values()->all();
    }

    /**
     * Query users managed by the current main admin without credentials.
     *
     * @return array<int, array<string, mixed>>
     */
    private function users(User $actor, string|null $search, int $limit): array
    {
        $query = User::query();
        User::scopeForAdmin($query, $actor);

        if ($search !== null) {
            $query->where('email', 'like', '%' . $search . '%');
        }

        return $query->orderBy('email')->limit($limit)->get()
            ->map(static fn(User $user): array => [
                'id' => $user->getKey(),
                'email' => $user->getEmail(),
                'is_admin' => $user->isAdmin(),
                'locale' => $user->getLocale(),
                'assigned_store_id' => $user->getAssignedStoreId(),
                'email_verified' => $user->getEmailVerifiedAt() !== null,
                'url' => $user->isAdmin()
                    ? Resolver::resolveUrlGenerator()->route('settings.show')
                    : Resolver::resolveUrlGenerator()->route('users.edit', $user->getKey()),
            ])->values()->all();
    }

    /**
     * Apply a model's explicit local search scope when a term is present.
     *
     * @param class-string $model
     * @param Builder<*> $query
     */
    private function search(Builder $query, string $model, string|null $search): void
    {
        if ($search !== null) {
            $model::scopeSearch($query, $search);
        }
    }
}
