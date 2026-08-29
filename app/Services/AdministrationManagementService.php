<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LimitedUserSectionEnum;
use App\Enums\StoreStatusEnum;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\OperationalDailyDigest;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Models\Worker;
use App\Notifications\SlackTestNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;

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

            if (!$store->isWarehouse()) {
                $checklists = new ChecklistService();
                $checklists->initializeStore($store);

                if ($store->getStatus() === StoreStatusEnum::ACTIVE) {
                    $checklists->ensureDay($store, CarbonImmutable::now(ChecklistService::TIMEZONE));
                }
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
        $store->update([
            'name' => $name,
            'address' => $address,
            'status' => $status,
            'notes' => $notes,
            'slack_channel' => $slackChannel,
            'is_warehouse' => $isWarehouse,
        ]);

        return $store->refresh();
    }

    /**
     * Delete an unused and unassigned store.
     */
    public function deleteStore(User $actor, Store $store): void
    {
        $this->authorizeStore($actor, $store);
        $hasMovements = StockMovement::query()
            ->where('user_id', $actor->getKey())
            ->where(static function (Builder $query) use ($store): void {
                $query->where('store_id', $store->getKey())
                    ->orWhere('source_store_id', $store->getKey());
            })
            ->exists();

        if ($store->storeItems()->exists() || $hasMovements || $store->assignedUser()->exists()) {
            Thrower::default()->message('store', \__('Cannot delete a store that has inventory or stock movement history.'))->throw();
        }

        $store->delete();
    }

    /**
     * Persist the main admin's active-store selection.
     */
    public function switchStore(User $actor, Store $store): void
    {
        $this->authorizeStore($actor, $store);
        $actor->setActiveStoreId($store->getKey());
    }

    /**
     * Create a limited account using the same persisted user shape as the web form.
     */
    public function createUser(User $actor, string $email, string $password, Store $assignedStore): User
    {
        $this->authorizeStore($actor, $assignedStore);

        return DB::transaction(static fn(): User => User::query()->create([
            'email' => $email,
            'password' => $password,
            'locale' => $actor->getLocale(),
            'is_admin' => false,
            'parent_user_id' => $actor->getKey(),
            'assigned_store_id' => $assignedStore->getKey(),
        ]));
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

        if ($assignedStore instanceof Store) {
            $this->authorizeStore($actor, $assignedStore);
        }

        DB::transaction(static function () use ($target, $email, $password, $assignedStore, $enabledSections, $isSelf): void {
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
     * Delete a worker only when no shift history references it.
     */
    public function deleteWorker(User $actor, Worker $worker): bool
    {
        $this->authorizeWorker($actor, $worker);
        $query = Shift::query();
        Shift::scopeForWorker($query, $worker->getKey());

        return !$query->exists() && $worker->delete();
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
}
