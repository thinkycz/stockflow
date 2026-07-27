<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Dashboard;

use App\Enums\NoticeboardCardColorEnum;
use App\Enums\NoticeboardCardLabelEnum;
use App\Enums\StockMovementTypeEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\NoticeboardCardValidity;
use App\Models\AttendanceSession;
use App\Models\InventorySession;
use App\Models\NoticeboardCard;
use App\Models\Shift;
use App\Models\Statement;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Models\Worker;
use App\Services\AttendanceService;
use App\Services\InventorySessionService;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;
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

        if (!$user->isAdmin()) {
            return Inertia::render('Dashboard', [
                'active_store' => $activeStore instanceof Store ? [
                    'id' => $activeStore->getKey(),
                    'name' => $activeStore->getName(),
                ] : null,
                'metrics' => null,
                'stock_status' => null,
                'top_consumed' => [],
                'recent_movements' => [],
                'recent_statements' => [],
                'operations' => $activeStore instanceof Store
                    ? $this->limitedOperations($user->resolveScopeUser(), $activeStore)
                    : null,
                'is_admin' => false,
                'noticeboard' => $noticeboard,
            ]);
        }

        $owner = $user->resolveScopeUser();
        $now = Carbon::now();
        $startOfToday = $now->copy()->startOfDay();
        $startOfTodayString = $startOfToday->toDateTimeString();
        $startOfMonthString = $now->copy()->startOfMonth()->toDateString();
        $thirtyDaysAgoString = $now->copy()->subDays(30)->toDateTimeString();

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

        $itemsCount = $itemsInStore->count();

        $stockStatus = [
            'in_stock' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
            'no_data' => 0,
        ];
        $lowStockCount = 0;
        $predictions = $inventoryService->predictionsForStore($activeStore, $itemsInStore);

        foreach ($itemsInStore as $row) {
            $prediction = $predictions[$row->getItemId()];

            if ($prediction['status'] === InventorySessionService::STATUS_OUT) {
                ++$stockStatus['out_of_stock'];
            } elseif ($prediction['status'] === InventorySessionService::STATUS_SOON) {
                ++$stockStatus['low_stock'];
                ++$lowStockCount;
            } elseif ($prediction['status'] === InventorySessionService::STATUS_NO_DATA) {
                ++$stockStatus['no_data'];
            } else {
                ++$stockStatus['in_stock'];
            }
        }

        $todayMovements = $this->countMovementsForStore($owner, $storeId, $startOfTodayString);

        $topConsumed = $this->topConsumed($owner, $storeId, $thirtyDaysAgoString);

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
            ->with(['store', 'creator'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static fn(StockMovement $movement): array => [
                'id' => $movement->getKey(),
                'number' => $movement->getNumber(),
                'type' => $movement->getType()->value,
                'store_name' => $movement->getStore()?->getName(),
                'total_quantity' => $movement->getTotalQuantity(),
                'total_value' => $movement->getTotalValue(),
                'created_at' => $movement->getOccurredAt()->toDateTimeString(),
            ])
            ->all();

        $recentStatementsQuery = Statement::query();
        Statement::scopeForUser($recentStatementsQuery, $owner);
        Statement::scopeForStore($recentStatementsQuery, $storeId);
        $recentStatements = $recentStatementsQuery
            ->withSum('days as period_total', 'total')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(static fn(Statement $statement): array => [
                'id' => $statement->getKey(),
                'year' => $statement->getYear(),
                'month' => $statement->getMonth(),
                'total' => Typer::parseFloat($statement->getAttribute('period_total')),
            ])
            ->all();

        $monthIncomingValue = $this->monthValueForStore($owner, $storeId, StockMovementTypeEnum::INCOMING, $startOfMonthString, 'store_id');
        $monthOutgoingValue = $this->consumptionValueForStore($owner, $storeId, $startOfMonthString);
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
                'items_count' => $itemsCount,
                'low_stock_items' => $lowStockCount,
                'today_movements' => $todayMovements,
                'month_incoming' => $monthIncomingValue,
                'month_outgoing' => $monthOutgoingValue,
                'last_inventory_at' => $lastInventory?->getCountedAt()->toJSON(),
            ],
            'stock_status' => $stockStatus,
            'top_consumed' => $topConsumed,
            'recent_movements' => $recentMovements,
            'recent_statements' => $recentStatements,
            'operations' => null,
            'is_admin' => true,
            'noticeboard' => $noticeboard,
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
     * Sum total_value of the given movement type scoped to the active
     * store since the supplied boundary.
     */
    private function monthValueForStore(
        User $user,
        int $storeId,
        StockMovementTypeEnum $type,
        string $since,
        string $storeColumn,
    ): float {
        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $user);
        StockMovement::scopeOfType($query, $type);
        StockMovement::scopeFromDate($query, $since);
        $query->whereNull('reversed_at');

        return (float) $query
            ->where($storeColumn, $storeId)
            ->sum('total_value');
    }

    /**
     * Top 5 consumed items for the active store.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topConsumed(User $user, int $storeId, string $since): array
    {
        $rows = DB::table('stock_movement_items')
            ->join('stock_movements as movements', 'movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->join('items', 'items.id', '=', 'stock_movement_items.item_id')
            ->where('items.user_id', $user->getKey())
            ->where('movements.user_id', $user->getKey())
            ->where('movements.store_id', $storeId)
            ->where('movements.occurred_at', '>=', $since)
            ->whereNull('movements.reversed_at')
            ->where('stock_movement_items.classification', 'consumption')
            ->select(
                'items.id',
                'items.title',
                'items.sku',
                DB::raw('SUM(ABS(stock_movement_items.quantity_difference)) as total_quantity'),
                DB::raw('SUM(stock_movement_items.total) as total_value'),
                DB::raw('COUNT(*) as rows_count'),
            )
            ->groupBy('items.id', 'items.title', 'items.sku')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        return $rows->map(static function (stdClass $row): array {
            $values = (array) $row;

            return [
                'item_id' => Typer::assertInt($values['id'] ?? null),
                'title' => Typer::assertString($values['title'] ?? null),
                'sku' => Typer::parseNullableString($values['sku'] ?? null),
                'total_quantity' => Typer::parseFloat($values['total_quantity'] ?? null),
                'total_value' => Typer::parseFloat($values['total_value'] ?? null),
                'rows_count' => Typer::parseInt($values['rows_count'] ?? null),
            ];
        })->all();
    }

    /**
     * Consumption value classified at line level.
     */
    private function consumptionValueForStore(User $user, int $storeId, string $since): float
    {
        return (float) DB::table('stock_movement_items')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->where('stock_movements.user_id', $user->getKey())
            ->where('stock_movements.store_id', $storeId)
            ->where('stock_movements.occurred_at', '>=', $since)
            ->whereNull('stock_movements.reversed_at')
            ->where('stock_movement_items.classification', 'consumption')
            ->sum('stock_movement_items.total');
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
                'items_count' => 0,
                'low_stock_items' => 0,
                'today_movements' => 0,
                'month_incoming' => 0.0,
                'month_outgoing' => 0.0,
                'last_inventory_at' => null,
            ],
            'stock_status' => [
                'in_stock' => 0,
                'low_stock' => 0,
                'out_of_stock' => 0,
                'no_data' => 0,
            ],
            'top_consumed' => [],
            'recent_movements' => [],
            'recent_statements' => [],
            'operations' => null,
            'is_admin' => true,
            'noticeboard' => $noticeboard,
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
                'can_view_trash' => $user->isAdmin(),
            ];
        }

        $query = $status === 'trash'
            ? NoticeboardCard::query()->onlyTrashed()
            : NoticeboardCard::query();
        NoticeboardCard::scopeForUser($query, $user->resolveScopeUser());
        NoticeboardCard::scopeForStore($query, $store->getKey());
        NoticeboardCard::querySelect($query);
        $query->with(['creator', 'updater']);

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
            $creator = $card->getCreator();
            $updater = $card->getUpdater();

            return [
                'id' => $card->getKey(),
                'title' => $card->getTitle(),
                'body_html' => $card->getBodyHtml(),
                'label' => $card->getLabel()->value,
                'color' => $card->getColor()->value,
                'image_url' => $card->getImagePath() === null
                    ? null
                    : Resolver::resolveUrlGenerator()->route('noticeboard-cards.image', $card->getKey()),
                'expires_on' => $card->getExpiresAt()?->setTimezone('Europe/Prague')->toDateString(),
                'created_at' => $card->getCreatedAt()->toJSON(),
                'updated_at' => $card->getUpdatedAt()->toJSON(),
                'deleted_at' => $card->getDeletedAt()?->toJSON(),
                'created_by_email' => $creator?->getEmail(),
                'updated_by_email' => $updater?->getEmail(),
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
