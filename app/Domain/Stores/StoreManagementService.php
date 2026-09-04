<?php

declare(strict_types=1);

namespace App\Domain\Stores;

use App\Domain\Checklists\ChecklistService;
use App\Enums\RemovalOutcomeEnum;
use App\Enums\StoreStatusEnum;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use App\Support\ChecklistDefaultTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class StoreManagementService
{
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
     * Ensure the assistant actor is the main administrator.
     */
    private function assertAdmin(User $actor): void
    {
        if (!$actor->isAdmin()) {
            \abort(403);
        }
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
