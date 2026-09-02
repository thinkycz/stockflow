<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LimitedUserSectionEnum;
use App\Enums\RemovalOutcomeEnum;
use App\Enums\StoreStatusEnum;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\OperationalDailyDigest;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Models\Worker;
use App\Notifications\SlackTestNotification;
use App\Support\ActiveStoreResolver;
use App\Support\ChecklistDefaultTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class AdministrationManagementService
{
    /**
     * Create an item and its zero-quantity warehouse row transactionally.
     */
    public function createItem(
        User $actor,
        string $title,
        string|null $sku,
        string|null $unit,
        string $purchasePrice,
        string|null $description,
    ): Item {
        $this->assertAdmin($actor);
        $warehouseId = $actor->warehouse()->getKey();

        return DB::transaction(static function () use ($actor, $title, $sku, $unit, $purchasePrice, $description, $warehouseId): Item {
            $item = Item::query()->create([
                'user_id' => $actor->getKey(),
                'title' => $title,
                'sku' => $sku,
                'unit' => $unit,
                'purchase_price' => $purchasePrice,
                'description' => $description,
            ]);
            StoreItem::query()->create([
                'store_id' => $warehouseId,
                'item_id' => $item->getKey(),
                'quantity' => 0,
            ]);

            return $item;
        });
    }

    /**
     * Update an owned item without changing stock quantities.
     */
    public function updateItem(
        User $actor,
        Item $item,
        string $title,
        string|null $sku,
        string|null $unit,
        string $purchasePrice,
        string|null $description,
    ): Item {
        $this->authorizeItem($actor, $item);
        $item->update([
            'title' => $title,
            'sku' => $sku,
            'unit' => $unit,
            'purchase_price' => $purchasePrice,
            'description' => $description,
        ]);

        return $item->refresh();
    }

    /**
     * Delete an unreferenced item and its draft/session rows.
     */
    public function deleteItem(User $actor, Item $item): void
    {
        $this->authorizeItem($actor, $item);
        $hasMovements = StockMovementItem::query()
            ->whereHas('stockMovement', static function (Builder $query) use ($item): void {
                $query->where('user_id', $item->getUserId());
            })
            ->where('item_id', $item->getKey())
            ->exists();

        if ($hasMovements) {
            Thrower::default()->message('item', \__('Cannot delete an item that has stock movement history.'))->throw();
        }

        DB::transaction(static function () use ($item): void {
            InventorySessionItem::query()
                ->where('item_id', $item->getKey())
                ->whereHas('session', static function (Builder $query): void {
                    $query->where('status', 'draft');
                })
                ->delete();
            $item->storeItems()->delete();
            $item->delete();
        });
    }

    /**
     * Create a store and initialize its checklist domain records.
     */
    public function createStore(
        User $actor,
        string $name,
        string|null $address,
        string $status,
        string|null $notes,
        string|null $slackChannel,
        bool $isWarehouse,
    ): Store {
        $this->assertAdmin($actor);

        if ($isWarehouse) {
            Thrower::default()->message('is_warehouse', \__('Additional warehouses cannot be created.'))->throw();
        }

        return DB::transaction(static function () use ($actor, $name, $address, $status, $notes, $slackChannel, $isWarehouse): Store {
            $store = Store::query()->create([
                'user_id' => $actor->getKey(),
                'name' => $name,
                'address' => $address,
                'status' => $status,
                'notes' => $notes,
                'slack_channel' => $slackChannel,
                'is_warehouse' => $isWarehouse,
            ]);

            if (!$store->isWarehouse() && $store->isActive()) {
                $checklists = new ChecklistService();
                $checklists->initializeStore($store);
                $checklists->ensureDay($store, CarbonImmutable::now(ChecklistService::TIMEZONE));
            }

            return $store;
        });
    }

    /**
     * Update an owned store.
     */
    public function updateStore(
        User $actor,
        Store $store,
        string $name,
        string|null $address,
        string $status,
        string|null $notes,
        string|null $slackChannel,
        bool $isWarehouse,
    ): Store {
        $this->authorizeStore($actor, $store);

        return DB::transaction(function () use ($actor, $store, $name, $address, $status, $notes, $slackChannel, $isWarehouse): Store {
            $lockedStore = Typer::assertInstance(Store::query()->lockForUpdate()->findOrFail($store->getKey()), Store::class);
            $this->authorizeStore($actor, $lockedStore);

            if ($isWarehouse !== $lockedStore->isWarehouse()) {
                Thrower::default()->message('is_warehouse', \__('The warehouse role cannot be changed.'))->throw();
            }

            if ($lockedStore->isWarehouse() && $status !== StoreStatusEnum::ACTIVE->value) {
                Thrower::default()->message('status', \__('The required warehouse must remain active.'))->throw();
            }

            if ($lockedStore->getStatus() === StoreStatusEnum::ACTIVE &&
                $status === StoreStatusEnum::INACTIVE->value &&
                $this->storeHasLiveWork($lockedStore)
            ) {
                Thrower::default()->message('status', \__('Resolve active store work before deactivating this store.'))->throw();
            }

            $wasInactive = !$lockedStore->isActive();
            $lockedStore->update([
                'name' => $name,
                'address' => $address,
                'status' => $status,
                'notes' => $notes,
                'slack_channel' => $slackChannel,
            ]);

            $lockedStore = $lockedStore->refresh();
            if ($wasInactive && $lockedStore->isActive() && !$lockedStore->isWarehouse()) {
                $checklists = new ChecklistService();
                $checklists->initializeStore($lockedStore);
                $checklists->ensureDay($lockedStore, CarbonImmutable::now(ChecklistService::TIMEZONE));
            }

            return $lockedStore;
        });
    }

    /**
     * Delete a pristine store, deactivate a historical store, or block live work.
     */
    public function deleteStore(User $actor, Store $store): RemovalOutcomeEnum
    {
        $this->authorizeStore($actor, $store);

        return DB::transaction(function () use ($actor, $store): RemovalOutcomeEnum {
            $lockedStore = Typer::assertInstance(Store::query()->lockForUpdate()->findOrFail($store->getKey()), Store::class);
            $this->authorizeStore($actor, $lockedStore);

            if ($this->storeHasLiveWork($lockedStore)) {
                return RemovalOutcomeEnum::BLOCKED;
            }

            if ($this->storeHasHistory($lockedStore)) {
                $lockedStore->update(['status' => StoreStatusEnum::INACTIVE->value]);

                return RemovalOutcomeEnum::ARCHIVED;
            }

            $lockedStore->storeItems()->where('quantity', 0)->delete();
            $lockedStore->delete();

            return RemovalOutcomeEnum::DELETED;
        });
    }

    /**
     * Persist the main admin's active-store selection in the originating session.
     */
    public function switchStore(User $actor, Store $store): void
    {
        $this->authorizeStore($actor, $store);
        if (!$store->isActive()) {
            \abort(404);
        }
        $browserSessionId = Typer::parseNullableString(Context::get(ActiveStoreResolver::SESSION_ID_CONTEXT));
        $request = Resolver::resolveRequest();

        if ($request->hasSession() && ($browserSessionId === null || \hash_equals($request->session()->getId(), $browserSessionId))) {
            $request->session()->put(ActiveStoreResolver::SESSION_KEY, $store->getKey());

            return;
        }

        if ($browserSessionId === null) {
            throw new RuntimeException('An active browser session is required to switch stores.');
        }

        $session = Resolver::resolveSessionStore();
        $session->flush();
        $session->setId($browserSessionId);
        $session->start();
        $session->put(ActiveStoreResolver::SESSION_KEY, $store->getKey());
        $session->save();
        $session->flush();
    }

    /**
     * Create a limited account using the same persisted user shape as the web form.
     */
    public function createUser(User $actor, string $email, string $password, Store $assignedStore): User
    {
        $this->authorizeStore($actor, $assignedStore);

        return DB::transaction(function () use ($actor, $email, $password, $assignedStore): User {
            $assignedStore = Typer::assertInstance(
                Store::query()->whereKey($assignedStore->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $this->authorizeStore($actor, $assignedStore);
            if (!$assignedStore->isActive() || $assignedStore->isWarehouse()) {
                \abort(404);
            }

            return User::query()->create([
                'email' => $email,
                'password' => $password,
                'locale' => $actor->getLocale(),
                'is_admin' => false,
                'parent_user_id' => $actor->getKey(),
                'assigned_store_id' => $assignedStore->getKey(),
            ]);
        });
    }

    /**
     * Update an account managed by the main admin.
     *
     * @param list<string>|null $enabledSections
     */
    public function updateUser(
        User $actor,
        User $target,
        string $email,
        string|null $password,
        Store|null $assignedStore,
        array|null $enabledSections,
    ): User {
        $this->authorizeManagedUser($actor, $target);
        $isSelf = $target->is($actor);

        if (!$isSelf && !$assignedStore instanceof Store) {
            \abort(422);
        }

        DB::transaction(function () use ($actor, $target, $email, $password, $assignedStore, $enabledSections, $isSelf): void {
            if (!$isSelf) {
                $assignedStore = Typer::assertInstance(
                    Store::query()->whereKey($assignedStore->getKey())->lockForUpdate()->firstOrFail(),
                    Store::class,
                );
                $this->authorizeStore($actor, $assignedStore);
                if (!$assignedStore->isActive() || $assignedStore->isWarehouse()) {
                    \abort(404);
                }
            }

            $target = Typer::assertInstance(User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail(), User::class);
            $this->authorizeManagedUser($actor, $target);
            $attributes = ['email' => $email];

            if ($password !== null && $password !== '') {
                $attributes['password'] = $password;
            }

            if (!$isSelf) {
                $attributes['assigned_store_id'] = $assignedStore->getKey();

                if ($enabledSections !== null) {
                    $attributes['disabled_sections'] = \array_values(\array_diff(
                        LimitedUserSectionEnum::values(),
                        $enabledSections,
                    ));
                }
            } else {
                $attributes['is_admin'] = true;
                $attributes['parent_user_id'] = null;
                $attributes['assigned_store_id'] = null;
            }

            $target->update($attributes);
        });

        return $target->refresh();
    }

    /**
     * Delete a limited account managed by the main admin.
     */
    public function deleteUser(User $actor, User $target): bool
    {
        $this->assertAdmin($actor);

        if ($target->is($actor) || $target->isAdmin()) {
            return false;
        }

        $this->authorizeManagedUser($actor, $target);

        return $target->delete();
    }

    /**
     * Create a worker record.
     */
    public function createWorker(
        User $actor,
        string $firstName,
        string $lastName,
        float $hourlyRate,
        string|null $calendarColor,
        bool $attendanceRatingEnabled,
    ): Worker {
        $this->assertAdmin($actor);

        return Worker::query()->create([
            'user_id' => $actor->getKey(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'hourly_rate' => $hourlyRate,
            'calendar_color' => Worker::normalizeCalendarColor($calendarColor),
            'attendance_rating_enabled' => $attendanceRatingEnabled,
            'archived_at' => null,
        ]);
    }

    /**
     * Update an owned worker record.
     */
    public function updateWorker(
        User $actor,
        Worker $worker,
        string $firstName,
        string $lastName,
        float $hourlyRate,
        string|null $calendarColor,
        bool $attendanceRatingEnabled,
    ): Worker {
        $this->authorizeWorker($actor, $worker);
        $worker->update([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'hourly_rate' => $hourlyRate,
            'calendar_color' => Worker::normalizeCalendarColor($calendarColor),
            'attendance_rating_enabled' => $attendanceRatingEnabled,
        ]);

        return $worker->refresh();
    }

    /**
     * Delete a pristine worker, archive a historical worker, or block live work.
     */
    public function deleteWorker(User $actor, Worker $worker): RemovalOutcomeEnum
    {
        $this->authorizeWorker($actor, $worker);

        return DB::transaction(function () use ($actor, $worker): RemovalOutcomeEnum {
            $lockedWorker = Typer::assertInstance(Worker::query()->lockForUpdate()->findOrFail($worker->getKey()), Worker::class);
            $this->authorizeWorker($actor, $lockedWorker);

            if ($lockedWorker->isArchived()) {
                return RemovalOutcomeEnum::ARCHIVED;
            }

            if ($this->workerHasLiveWork($lockedWorker)) {
                return RemovalOutcomeEnum::BLOCKED;
            }

            if ($this->workerHasHistory($lockedWorker)) {
                $lockedWorker->update(['archived_at' => CarbonImmutable::now()]);

                return RemovalOutcomeEnum::ARCHIVED;
            }

            $lockedWorker->delete();

            return RemovalOutcomeEnum::DELETED;
        });
    }

    /**
     * Return an archived worker to prospective work selectors.
     */
    public function restoreWorker(User $actor, Worker $worker): Worker
    {
        $this->authorizeWorker($actor, $worker);

        return DB::transaction(function () use ($actor, $worker): Worker {
            $lockedWorker = Typer::assertInstance(Worker::query()->lockForUpdate()->findOrFail($worker->getKey()), Worker::class);
            $this->authorizeWorker($actor, $lockedWorker);
            $lockedWorker->update(['archived_at' => null]);

            return $lockedWorker->refresh();
        });
    }

    /**
     * Update the main admin's email and locale.
     */
    public function updateProfile(User $actor, string $email, string $locale): User
    {
        $this->assertAdmin($actor);
        $actor->update(['email' => $email, 'locale' => $locale]);

        return $actor->refresh();
    }

    /**
     * Update the company-wide Slack destination.
     */
    public function updateSlackChannel(User $actor, string|null $channel): User
    {
        $this->assertAdmin($actor);
        $actor->update(['company_slack_channel' => $channel]);

        return $actor->refresh();
    }

    /**
     * Send the normal test notification to the configured Slack channel.
     */
    public function testSlackChannel(User $actor): bool
    {
        $this->assertAdmin($actor);
        $channel = \mb_trim($actor->getCompanySlackChannel() ?? '');

        if ($channel === '') {
            return false;
        }

        Resolver::resolveNotificationFactory()->send(
            (new AnonymousNotifiable())->route('slack', $channel),
            new SlackTestNotification($actor->getEmail()),
        );

        return true;
    }

    /**
     * Requeue one failed daily Slack digest through its normal service.
     */
    public function retrySlackDigest(User $actor, OperationalDailyDigest $digest): void
    {
        $this->assertAdmin($actor);

        if ($digest->getCompanyUserId() !== $actor->getKey()) {
            \abort(404);
        }

        (new DailyOperationalDigestService())->retry($actor, $digest);
    }

    /**
     * Ensure the assistant actor is the main administrator.
     */
    private function assertAdmin(User $actor): void
    {
        if (!$actor->isAdmin()) {
            \abort(403);
        }
    }

    /**
     * Ensure an item belongs to the main administrator.
     */
    private function authorizeItem(User $actor, Item $item): void
    {
        $this->assertAdmin($actor);

        if ($item->getUserId() !== $actor->getKey()) {
            \abort(404);
        }
    }

    /**
     * Ensure a store belongs to the main administrator.
     */
    private function authorizeStore(User $actor, Store $store): void
    {
        $this->assertAdmin($actor);

        if ($store->getUserId() !== $actor->getKey()) {
            \abort(404);
        }
    }

    /**
     * Ensure a user belongs to the main admin's managed tree.
     */
    private function authorizeManagedUser(User $actor, User $target): void
    {
        $this->assertAdmin($actor);

        if (!$target->is($actor) && $target->getParentUserId() !== $actor->getKey()) {
            \abort(403);
        }
    }

    /**
     * Ensure a worker belongs to the main administrator.
     */
    private function authorizeWorker(User $actor, Worker $worker): void
    {
        $this->assertAdmin($actor);

        if ($worker->getUserId() !== $actor->getKey()) {
            \abort(404);
        }
    }

    /**
     * Live attendance and future scheduling must be resolved before archival.
     */
    private function workerHasLiveWork(Worker $worker): bool
    {
        $workerId = $worker->getKey();
        $today = CarbonImmutable::today()->toDateString();

        if (DB::table('attendance_sessions')->where('worker_id', $workerId)->whereNull('ended_at')->whereNull('voided_at')->exists()) {
            return true;
        }

        if (DB::table('attendance_sessions')->where('active_worker_id', $workerId)->whereNull('ended_at')->whereNull('voided_at')->exists()) {
            return true;
        }

        if (DB::table('shifts')->where('worker_id', $workerId)->whereDate('date', '>=', $today)->exists()) {
            return true;
        }

        return DB::table('shift_requests')->where('worker_id', $workerId)->whereDate('date', '>=', $today)->exists();
    }

    /**
     * Any historical reference keeps the worker row and its identity intact.
     */
    private function workerHasHistory(Worker $worker): bool
    {
        $workerId = $worker->getKey();
        $references = [
            ['shifts', 'worker_id'],
            ['shift_requests', 'worker_id'],
            ['attendance_sessions', 'worker_id'],
            ['attendance_sessions', 'active_worker_id'],
            ['payroll_adjustments', 'worker_id'],
            ['payroll_wage_overrides', 'worker_id'],
            ['payroll_worker_entries', 'worker_id'],
            ['checklist_items', 'completed_by_worker_id'],
            ['checklist_events', 'worker_id'],
            ['recipe_test_attempts', 'worker_id'],
            ['recipe_test_sessions', 'worker_id'],
        ];

        foreach ($references as [$table, $column]) {
            if (DB::table($table)->where($column, $workerId)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Store state that must be resolved rather than implicitly cancelled.
     */
    private function storeHasLiveWork(Store $store): bool
    {
        $storeId = $store->getKey();
        $today = CarbonImmutable::today()->toDateString();

        if ($store->isWarehouse() || $store->assignedUser()->exists() || $store->storeItems()->where('quantity', '!=', 0)->exists()) {
            return true;
        }

        if (DB::table('inventory_sessions')->where('store_id', $storeId)->where('status', 'draft')->exists()) {
            return true;
        }

        if (DB::table('attendance_sessions')->where('store_id', $storeId)->whereNull('ended_at')->whereNull('voided_at')->exists()) {
            return true;
        }

        if (DB::table('shifts')->where('store_id', $storeId)->whereDate('date', '>=', $today)->exists()) {
            return true;
        }

        if (DB::table('shift_requests')->where('store_id', $storeId)->whereDate('date', '>=', $today)->exists()) {
            return true;
        }

        return DB::table('bank_statements')->where('store_id', $storeId)->whereIn('status', ['queued', 'processing', 'review'])->exists();
    }

    /**
     * Historical and manually configured records require deactivation, not cascading deletion.
     */
    private function storeHasHistory(Store $store): bool
    {
        $storeId = $store->getKey();
        $references = [
            ['stock_movements', 'store_id'],
            ['stock_movements', 'source_store_id'],
            ['inventory_sessions', 'store_id'],
            ['statements', 'store_id'],
            ['shifts', 'store_id'],
            ['shift_presets', 'store_id'],
            ['attendance_sessions', 'store_id'],
            ['attendance_deviation_reviews', 'store_id'],
            ['financial_reports', 'store_id'],
            ['financial_recurring_expenses', 'store_id'],
            ['payroll_reports', 'store_id'],
            ['noticeboard_cards', 'store_id'],
            ['gift_vouchers', 'redeemed_store_id'],
            ['gift_voucher_events', 'store_id'],
            ['bank_statements', 'store_id'],
            ['shift_requests', 'store_id'],
            ['shift_request_month_locks', 'store_id'],
            ['shift_share_links', 'store_id'],
            ['assistant_action_audits', 'store_id'],
        ];

        foreach ($references as [$table, $column]) {
            if (DB::table($table)->where($column, $storeId)->exists()) {
                return true;
            }
        }

        if ($this->storeHasChecklistHistory($storeId)) {
            return true;
        }

        return false;
    }

    /**
     * Ignore untouched generated checklist scaffolding while preserving real checklist history.
     */
    private function storeHasChecklistHistory(int $storeId): bool
    {
        if (DB::table('checklist_days')
            ->where('store_id', $storeId)
            ->where(static function (QueryBuilder $query): void {
                $query->whereDate('date', '<', CarbonImmutable::now(ChecklistService::TIMEZONE)->toDateString())
                    ->orWhereNotNull('excused_at');
            })
            ->exists()
        ) {
            return true;
        }

        if (DB::table('checklist_items')
            ->join('checklist_days', 'checklist_days.id', '=', 'checklist_items.checklist_day_id')
            ->where('checklist_days.store_id', $storeId)
            ->whereNotNull('checklist_items.completed_at')
            ->exists()
        ) {
            return true;
        }

        if (DB::table('checklist_events')
            ->join('checklist_days', 'checklist_days.id', '=', 'checklist_events.checklist_day_id')
            ->where('checklist_days.store_id', $storeId)
            ->exists()
        ) {
            return true;
        }

        $actual = DB::table('checklist_template_tasks')
            ->where('store_id', $storeId)
            ->orderBy('scope')
            ->orderBy('weekday')
            ->orderBy('shift')
            ->orderBy('position')
            ->get(['scope', 'weekday', 'shift', 'text', 'position'])
            ->map(static fn(object $task): array => [
                'scope' => Typer::assertString($task->scope),
                'weekday' => Typer::parseNullableInt($task->weekday),
                'shift' => Typer::assertString($task->shift),
                'text' => Typer::assertString($task->text),
                'position' => Typer::parseInt($task->position),
            ])
            ->all();
        $expected = ChecklistDefaultTemplate::tasks();
        $sort = static fn(array $left, array $right): int => \json_encode($left, \JSON_THROW_ON_ERROR) <=> \json_encode($right, \JSON_THROW_ON_ERROR);
        \usort($actual, $sort);
        \usort($expected, $sort);

        return $actual !== [] && $actual !== $expected;
    }
}
