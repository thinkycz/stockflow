<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Dashboard;

use App\Enums\NoticeboardCardColorEnum;
use App\Enums\NoticeboardCardLabelEnum;
use App\Enums\NoticeboardCardSizeEnum;
use App\Enums\StockMovementTypeEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\NoticeboardCardValidity;
use App\Models\AttendanceSession;
use App\Models\InventorySession;
use App\Models\NoticeboardCard;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Models\Worker;
use App\Services\AttendanceService;
use App\Services\ChecklistService;
use App\Services\InventorySessionService;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class DashboardController
{
    use ValidatesWebRequests;

    /**
     * Render the dashboard for the currently active store.
     *
     * Administrators receive metrics scoped to the active store resolved via
     * `ActiveStoreResolver`. Limited users receive only their store context;
     * the frontend renders the available action shortcuts without statistics.
     */
    public function __invoke(Request $request, InventorySessionService $inventoryService): Response
    {
        $user = User::mustAuth();
        $activeStore = ActiveStoreResolver::resolve($request, $user);
        $noticeboard = $this->noticeboard($request, $user, $activeStore);
        $checklists = $activeStore instanceof Store ? (new ChecklistService())->dashboardPayload($activeStore, $user) : null;

        if (!$user->isAdmin()) {
            return Inertia::render('Dashboard', [
                'active_store' => $activeStore instanceof Store ? [
                    'id' => $activeStore->getKey(),
                    'name' => $activeStore->getName(),
                ] : null,
                'metrics' => null,
                'recent_movements' => [],
                'operations' => $activeStore instanceof Store
                    ? $this->limitedOperations($user->resolveScopeUser(), $activeStore)
                    : null,
                'is_admin' => false,
                'noticeboard' => $noticeboard,
                'checklists' => $checklists,
            ]);
        }

        $owner = $user->resolveScopeUser();
        $now = Carbon::now();
        $startOfToday = $now->copy()->startOfDay();
        $startOfTodayString = $startOfToday->toDateTimeString();

        if (!$activeStore instanceof Store) {
            return Inertia::render('Dashboard', $this->emptyPayload($noticeboard));
        }

        $storeId = $activeStore->getKey();

        $inventoryValue = (float) DB::table('store_items')
            ->join('items', 'items.id', '=', 'store_items.item_id')
            ->where('items.user_id', $owner->getKey())
            ->where('store_items.store_id', $storeId)
            ->sum(DB::raw('store_items.quantity * items.purchase_price'));

        $itemsInStore = StoreItem::query()->where('store_id', $storeId)->with('item')->get();

        $lowStockCount = 0;
        $predictions = $inventoryService->predictionsForStore($activeStore, $itemsInStore);

        foreach ($itemsInStore as $row) {
            $prediction = $predictions[$row->getItemId()];

            if ($prediction['status'] === InventorySessionService::STATUS_SOON) {
                ++$lowStockCount;
            }
        }

        $todayMovements = $this->countMovementsForStore($owner, $storeId, $startOfTodayString);

        $recentMovements = StockMovement::query();
        StockMovement::scopeForUser($recentMovements, $owner);
        $recentMovements->where(static function (Builder $query) use ($storeId): void {
            $query
                ->where(static function (Builder $query) use ($storeId): void {
                    $query
                        ->where('type', StockMovementTypeEnum::INCOMING->value)
                        ->where('store_id', $storeId);
                })
                ->orWhere(static function (Builder $query) use ($storeId): void {
                    $query
                        ->where('type', StockMovementTypeEnum::TRANSFER->value)
                        ->where(static function (Builder $query) use ($storeId): void {
                            $query->where('source_store_id', $storeId)->orWhere('store_id', $storeId);
                        });
                })
                ->orWhere(static function (Builder $query) use ($storeId): void {
                    $query
                        ->whereIn('type', [StockMovementTypeEnum::ADJUSTMENT->value, StockMovementTypeEnum::CONSUMPTION->value, StockMovementTypeEnum::INVENTORY_RECONCILIATION->value])
                        ->where('store_id', $storeId);
                });
        });
        $recentMovements = $recentMovements
            ->whereNull('reversed_at')
            ->with(['store', 'sourceStore', 'creator'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static fn(StockMovement $movement): array => [
                'id' => $movement->getKey(),
                'number' => $movement->getNumber(),
                'type' => $movement->getType()->value,
                'display_label_key' => $movement->getDisplayLabelKey(),
                'store_name' => $movement->getStore()?->getName(),
                'total_quantity' => $movement->getTotalQuantity(),
                'total_value' => $movement->getTotalValue(),
                'created_at' => $movement->getOccurredAt()->toDateTimeString(),
            ])
            ->all();

        $lastInventory = InventorySession::query()
            ->where('user_id', $owner->getKey())
            ->where('store_id', $storeId)
            ->where('status', 'closed')
            ->orderByDesc('counted_at')
            ->first();

        return Inertia::render('Dashboard', [
            'active_store' => [
                'id' => $activeStore->getKey(),
                'name' => $activeStore->getName(),
            ],
            'metrics' => [
                'inventory_value' => $inventoryValue,
                'low_stock_items' => $lowStockCount,
                'today_movements' => $todayMovements,
                'last_inventory_at' => $lastInventory?->getCountedAt()->toJSON(),
            ],
            'recent_movements' => $recentMovements,
            'operations' => null,
            'is_admin' => true,
            'noticeboard' => $noticeboard,
            'checklists' => $checklists,
        ]);
    }

    /**
     * Count stock movements touching the given store (incoming as
     * destination, outgoing as source, adjustment as either) created
     * on or after `$since`.
     */
    private function countMovementsForStore(User $user, int $storeId, string $since): int
    {
        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $user);
        StockMovement::scopeFromDate($query, $since);
        $query->whereNull('reversed_at');
        $query->where(static function (Builder $query) use ($storeId): void {
            $query
                ->where(static function (Builder $query) use ($storeId): void {
                    $query
                        ->where('type', StockMovementTypeEnum::INCOMING->value)
                        ->where('store_id', $storeId);
                })
                ->orWhere(static function (Builder $query) use ($storeId): void {
                    $query
                        ->where('type', StockMovementTypeEnum::TRANSFER->value)
                        ->where(static function (Builder $query) use ($storeId): void {
                            $query->where('source_store_id', $storeId)->orWhere('store_id', $storeId);
                        });
                })
                ->orWhere(static function (Builder $query) use ($storeId): void {
                    $query
                        ->whereIn('type', [StockMovementTypeEnum::ADJUSTMENT->value, StockMovementTypeEnum::CONSUMPTION->value, StockMovementTypeEnum::INVENTORY_RECONCILIATION->value])
                        ->where('store_id', $storeId);
                });
        });

        return $query->count();
    }

    /**
     * Payload returned when the user has no active store. Numeric
     * metrics are zeroed out and lists are empty so the page can
     * render without errors.
     *
     * @param array<string, mixed> $noticeboard
     *
     * @return array<string, mixed>
     */
    private function emptyPayload(array $noticeboard): array
    {
        return [
            'active_store' => null,
            'metrics' => [
                'inventory_value' => 0.0,
                'low_stock_items' => 0,
                'today_movements' => 0,
                'last_inventory_at' => null,
            ],
            'recent_movements' => [],
            'operations' => null,
            'is_admin' => true,
            'noticeboard' => $noticeboard,
            'checklists' => null,
        ];
    }

    /**
     * Build the paginated noticeboard payload for the current store.
     *
     * @return array<string, mixed>
     */
    private function noticeboard(Request $request, User $user, Store|null $store): array
    {
        $validity = NoticeboardCardValidity::inject();
        $validated = $this->validateRequest($request, [
            'status' => $validity->status()->nullable()->toArray(),
            'label' => $validity->label()->nullable()->toArray(),
            'search' => $validity->search()->nullable()->toArray(),
        ]);
        $status = $validated->assertNullableString('status') ?? 'active';
        $label = $validated->assertNullableString('label');
        $search = $validated->assertNullableString('search') ?? '';

        if ($status === 'trash' && !$user->isAdmin()) {
            \abort(403);
        }

        if (!$store instanceof Store) {
            return [
                'cards' => [],
                'filters' => ['status' => $status, 'label' => $label, 'search' => $search],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 24, 'total' => 0],
                'labels' => NoticeboardCardLabelEnum::values(),
                'colors' => NoticeboardCardColorEnum::values(),
                'sizes' => NoticeboardCardSizeEnum::values(),
                'can_view_trash' => $user->isAdmin(),
            ];
        }

        $query = $status === 'trash'
            ? NoticeboardCard::query()->onlyTrashed()
            : NoticeboardCard::query();
        NoticeboardCard::scopeForUser($query, $user->resolveScopeUser());
        NoticeboardCard::scopeForStore($query, $store->getKey());
        NoticeboardCard::querySelect($query);

        if ($status === 'active') {
            $query->where(static function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now()->utc());
            });
        } elseif ($status === 'expired') {
            $query->whereNotNull('expires_at')->where('expires_at', '<=', Carbon::now()->utc());
        }

        if ($label !== null) {
            $query->where('label', $label);
        }

        if ($search !== '') {
            NoticeboardCard::scopeSearch($query, $search);
        }

        $paginator = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(24)->withQueryString();
        $cards = $paginator->getCollection()->map(static function (NoticeboardCard $card): array {
            return [
                'id' => $card->getKey(),
                'body_html' => $card->getBodyHtml(),
                'label' => $card->getLabel()->value,
                'color' => $card->getColor()->value,
                'size' => $card->getSize()->value,
                'image_url' => $card->getImagePath() === null
                    ? null
                    : Resolver::resolveUrlGenerator()->route('noticeboard-cards.image', $card->getKey()),
                'expires_on' => $card->getExpiresAt()?->setTimezone('Europe/Prague')->toDateString(),
                'version' => $card->getLockVersion(),
            ];
        })->all();

        return [
            'cards' => $cards,
            'filters' => ['status' => $status, 'label' => $label, 'search' => $search],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'labels' => NoticeboardCardLabelEnum::values(),
            'colors' => NoticeboardCardColorEnum::values(),
            'sizes' => NoticeboardCardSizeEnum::values(),
            'can_view_trash' => $user->isAdmin(),
        ];
    }

    /**
     * Build the non-financial live operations summary for a limited user's store.
     *
     * @return array{
     *     current_shifts: list<array{id: int, worker_name: string, start_time: string, end_time: string}>,
     *     next_shift: array{id: int, worker_name: string, date: string, start_time: string, end_time: string}|null,
     *     attendance: array{workers: list<array{worker_name: string, status: 'break'|'present'}>, stale_count: int}
     * }
     */
    private function limitedOperations(User $owner, Store $store): array
    {
        $now = CarbonImmutable::now(AttendanceService::BUSINESS_TIMEZONE);
        $today = $now->toDateString();
        $time = $now->format('H:i:s');

        $shiftQuery = Shift::query();
        Shift::scopeForUser($shiftQuery, $owner);
        Shift::scopeForStore($shiftQuery, $store->getKey());
        Shift::querySelect($shiftQuery);

        $currentShifts = (clone $shiftQuery)
            ->whereDate('date', $today)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>', $time)
            ->orderBy('start_time')
            ->get();
        $nextShift = (clone $shiftQuery)
            ->where(static function (Builder $query) use ($today, $time): void {
                $query->whereDate('date', '>', $today)
                    ->orWhere(static function (Builder $query) use ($today, $time): void {
                        $query->whereDate('date', $today)->where('start_time', '>', $time);
                    });
            })
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        $attendanceQuery = AttendanceSession::query();
        AttendanceSession::scopeForUser($attendanceQuery, $owner);
        AttendanceSession::scopeForStore($attendanceQuery, $store->getKey());
        AttendanceSession::querySelect($attendanceQuery);
        $attendanceSessions = $attendanceQuery->whereNotNull('active_worker_id')->get();
        $attendanceSessionIds = $attendanceSessions
            ->map(static fn(AttendanceSession $session): int => $session->getKey())
            ->all();
        $breakSessionIds = DB::table('attendance_breaks')
            ->whereIn('active_session_id', $attendanceSessionIds)
            ->pluck('active_session_id')
            ->map(static fn(mixed $id): int => Typer::parseInt($id))
            ->all();

        $workerIds = [
            ...$currentShifts->map(static fn(Shift $shift): int => $shift->getWorkerId())->all(),
            ...$attendanceSessions->map(static fn(AttendanceSession $session): int => $session->getWorkerId())->all(),
        ];
        if ($nextShift instanceof Shift) {
            $workerIds[] = $nextShift->getWorkerId();
        }

        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $owner);
        Worker::querySelect($workerQuery);
        $workers = $workerQuery->whereKey(\array_values(\array_unique($workerIds)))->get()->keyBy(
            static fn(Worker $worker): int => $worker->getKey(),
        );

        $currentShiftRows = [];
        foreach ($currentShifts as $shift) {
            $worker = $workers->get($shift->getWorkerId());
            if ($worker instanceof Worker) {
                $currentShiftRows[] = [
                    'id' => $shift->getKey(),
                    'worker_name' => $worker->getFullName(),
                    'start_time' => $shift->getStartTimeShort(),
                    'end_time' => $shift->getEndTimeShort(),
                ];
            }
        }

        $attendanceRows = [];
        $staleCount = 0;
        foreach ($attendanceSessions as $session) {
            if ($today !== $session->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toDateString()) {
                ++$staleCount;

                continue;
            }

            $worker = $workers->get($session->getWorkerId());
            if ($worker instanceof Worker) {
                $attendanceRows[] = [
                    'worker_name' => $worker->getFullName(),
                    'status' => \in_array($session->getKey(), $breakSessionIds, true) ? 'break' : 'present',
                ];
            }
        }

        $nextWorker = $nextShift instanceof Shift ? $workers->get($nextShift->getWorkerId()) : null;

        return [
            'current_shifts' => $currentShiftRows,
            'next_shift' => $nextShift instanceof Shift && $nextWorker instanceof Worker ? [
                'id' => $nextShift->getKey(),
                'worker_name' => $nextWorker->getFullName(),
                'date' => $nextShift->getDate(),
                'start_time' => $nextShift->getStartTimeShort(),
                'end_time' => $nextShift->getEndTimeShort(),
            ] : null,
            'attendance' => [
                'workers' => $attendanceRows,
                'stale_count' => $staleCount,
            ],
        ];
    }
}
